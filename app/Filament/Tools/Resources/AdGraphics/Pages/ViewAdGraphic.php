<?php

namespace App\Filament\Tools\Resources\AdGraphics\Pages;

use App\Filament\Tools\Pages\Agent\AgentProject;
use App\Filament\Tools\Resources\AdGraphics\AdGraphicResource;
use App\Models\AdGraphic;
use App\Support\AdGraphics\GraphicsAgent;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * Detail zadání: stav práce agenta, návrhy po kolech (první zadání a úpravy),
 * odkaz do Figmy a tlačítko na další úpravu.
 */
class ViewAdGraphic extends ViewRecord
{
    protected static string $resource = AdGraphicResource::class;

    protected string $view = 'filament.tools.resources.ad-graphics.view';

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Úprava má vlastní záznam, ale ukazuje se v detailu prvního zadání.
        if ($this->getRecord()->parent_id) {
            $this->redirect(AdGraphicResource::getUrl('view', ['record' => $this->getRecord()->parent_id]));
        }
    }

    public function getTitle(): string
    {
        return $this->getRecord()->title;
    }

    public function getSubheading(): ?string
    {
        $record = $this->getRecord();

        return collect([$record->client?->name, $record->formatsShort(), 'zadal '.($record->author ?: '?').' '.$record->created_at->format('j. n. H:i')])->filter()->implode(' · ');
    }

    /** @return Collection<int, AdGraphic> */
    public function rounds(): Collection
    {
        return $this->getRecord()->rounds();
    }

    public function latest(): AdGraphic
    {
        return $this->rounds()->last();
    }

    public function imageUrl(AdGraphic $round, string $file): string
    {
        return route('filament.tools.agent.snimek', ['project' => $round->project, 'name' => $round->folder(), 'file' => $file]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('figma')
                ->label('Otevřít ve Figmě')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->visible(fn (): bool => filled($this->figmaUrl()))
                ->url(fn (): ?string => $this->figmaUrl(), shouldOpenInNewTab: true),
            Action::make('conversation')
                ->label('Konverzace s agentem')
                ->icon(Heroicon::OutlinedCommandLine)
                ->color('gray')
                ->url(fn (): string => AgentProject::getUrl(['project' => $this->getRecord()->project])),
            Action::make('retry')
                ->label('Zkusit znovu')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible(fn (): bool => $this->latest()->isFailed() && blank($this->latest()->outputs))
                ->requiresConfirmation()
                ->modalDescription('Agent začne toto kolo znovu od začátku.')
                ->action(function (): void {
                    app(GraphicsAgent::class)->start($this->latest());
                    $this->notifyStart($this->latest()->refresh());
                }),
            Action::make('revise')
                ->label('Upravit návrhy')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->visible(fn (): bool => ! $this->latest()->isRunning() && $this->rounds()->contains(fn (AdGraphic $round): bool => filled($round->outputs)))
                ->modalHeading('Co upravit')
                ->modalDescription('Agent naváže na předchozí práci a připraví nové verze. Původní návrhy zůstanou.')
                ->modalSubmitActionLabel('Poslat agentovi')
                ->schema([
                    Textarea::make('instruction')
                        ->label('Pokyn')
                        ->placeholder('U trojky větší cenu a méně textu. Jedničku udělej ještě jednou jako Story. Přidej variantu s dárkem ke každé objednávce.')
                        ->rows(5)
                        ->required()
                        ->maxLength(3000),
                ])
                ->action(function (array $data): void {
                    $revision = app(GraphicsAgent::class)->revise($this->getRecord(), $data['instruction'], auth()->user()?->name ?? 'nástroje');
                    $this->notifyStart($revision);
                }),
        ];
    }

    private function figmaUrl(): ?string
    {
        return $this->rounds()->pluck('figma_url')->filter()->last();
    }

    private function notifyStart(AdGraphic $round): void
    {
        $round->isFailed()
            ? Notification::make()->title('Agenta se nepodařilo spustit')->body($round->error)->danger()->send()
            : Notification::make()->title('Agent se pustil do práce')->body('Trvá to zhruba 10–30 minut, stránka se obnoví sama.')->success()->send();
    }
}
