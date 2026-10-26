<?php

namespace App\Filament\Tools\Resources\KnowledgeSources\Pages;

use App\Filament\Tools\Pages\Agent\AgentProject;
use App\Filament\Tools\Resources\KnowledgeEntries\KnowledgeEntryResource;
use App\Filament\Tools\Resources\KnowledgeSources\KnowledgeSourceResource;
use App\Models\KnowledgeEntry;
use App\Support\Agent\AgentServer;
use App\Support\Knowledge\BrainAgent;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Detail podkladů: co v nich je (popis pro archiv, soubory ke stažení),
 * co agent dělá (terminál), jeho shrnutí a návrhy poznatků ke schválení.
 */
class ViewKnowledgeSource extends ViewRecord
{
    protected static string $resource = KnowledgeSourceResource::class;

    protected string $view = 'filament.tools.resources.knowledge-sources.view';

    public function getTitle(): string
    {
        return $this->getRecord()->title;
    }

    public function getSubheading(): ?string
    {
        $record = $this->getRecord();

        return collect([$record->client?->name, $record->categoryLabel(), $record->document_date ? 'k '.$record->document_date->format('j. n. Y') : null, 'nahrál '.($record->author ?: '?').' '.$record->created_at->format('j. n. Y')])->filter()->implode(' · ');
    }

    /** @return Collection<int, KnowledgeEntry> */
    #[Computed]
    public function entries(): Collection
    {
        return $this->getRecord()->entries()->with(['client', 'replaces'])->orderByRaw('status = ? desc', [KnowledgeEntry::STATUS_PROPOSED])->orderBy('id')->get();
    }

    /** Nahraný soubor zpátky k člověku (archiv klienta). */
    public function download(int $index): ?StreamedResponse
    {
        $file = ($this->getRecord()->files ?? [])[$index] ?? null;

        return is_array($file) && Storage::disk('local')->exists($file['path'] ?? '')
            ? Storage::disk('local')->download($file['path'], $file['name'] ?? basename($file['path']))
            : null;
    }

    public function approve(int $id): void
    {
        $this->getRecord()->entries()->proposed()->find($id)?->approve(KnowledgeEntryResource::reviewer());
        unset($this->entries);
    }

    public function reject(int $id): void
    {
        $this->getRecord()->entries()->proposed()->find($id)?->reject(KnowledgeEntryResource::reviewer());
        unset($this->entries);
    }

    /** Terminál běžící úlohy, jako u reklamní grafiky. Null: ve frontě, doběhla, nebo bez okna. */
    public function terminalUrl(bool $write = false): ?string
    {
        $record = $this->getRecord()->refresh();
        $session = $record->isRunning() ? $record->agentJob?->session : null;

        if ($session === null) {
            return null;
        }

        $agent = app(AgentServer::class);
        $window = rescue(fn (): ?string => collect($agent->conversations(BrainAgent::project()))->whereNotNull('okno')->firstWhere('session', $session)['okno'] ?? null, null, report: false);

        return $window ? $agent->terminalUrl(BrainAgent::project(), $window, (string) auth()->user()?->name, request()->getHost(), readOnly: ! $write) : null;
    }

    protected function getHeaderActions(): array
    {
        $proposed = fn (): int => $this->getRecord()->entries()->proposed()->count();

        return [
            Action::make('approveAll')
                ->label(fn (): string => 'Schválit všechny ('.$proposed().')')
                ->icon(Heroicon::OutlinedCheck)
                ->color('success')
                ->visible(fn (): bool => $proposed() > 0 && ! $this->getRecord()->isRunning())
                ->requiresConfirmation()
                ->modalDescription('Všechny návrhy z těchto podkladů začnou platit a agenti je budou dostávat. Jednotlivé můžete předtím upravit nebo zamítnout.')
                ->action(function (): void {
                    $this->getRecord()->entries()->proposed()->get()->each(fn (KnowledgeEntry $entry) => $entry->approve(KnowledgeEntryResource::reviewer()));
                    unset($this->entries);
                }),
            // Méně časté věci v nabídce, ať se vedle nadpisu nemačká pět tlačítek.
            ActionGroup::make([
                Action::make('conversation')
                    ->label('Konverzace s agentem')
                    ->icon(Heroicon::OutlinedCommandLine)
                    ->color('gray')
                    ->url(fn (): string => AgentProject::getUrl(['project' => BrainAgent::project()])),
                Action::make('retry')
                    ->label(fn (): string => match (true) {
                        $this->getRecord()->isFailed() => 'Zkusit znovu',
                        $this->getRecord()->isStored() => 'Udělat z nich poznatky',
                        default => 'Projít znovu',
                    })
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('gray')
                    ->visible(fn (): bool => ! $this->getRecord()->isRunning())
                    ->requiresConfirmation()
                    ->modalDescription('Agent podklady přečte znovu od začátku. Co už v mozku je (i ke schválení), znovu nezaloží.')
                    ->action(function (): void {
                        app(BrainAgent::class)->process($this->getRecord());
                        $this->getRecord()->refresh()->isFailed()
                            ? Notification::make()->title('Agenta se nepodařilo spustit')->body($this->getRecord()->error)->danger()->send()
                            : Notification::make()->title('Agent podklady čte')->success()->send();
                    }),
                KnowledgeSourceResource::deleteAction(DeleteAction::make())->label('Smazat'),
            ])
                ->label('Další')
                ->icon(Heroicon::OutlinedEllipsisHorizontal)
                ->button()
                ->color('gray'),
        ];
    }
}
