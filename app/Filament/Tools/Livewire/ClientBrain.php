<?php

namespace App\Filament\Tools\Livewire;

use App\Filament\Tools\Resources\Clients\ClientResource;
use App\Filament\Tools\Resources\KnowledgeEntries\KnowledgeEntryResource;
use App\Filament\Tools\Resources\KnowledgeSources\KnowledgeSourceResource;
use App\Filament\Tools\Resources\Skills\SkillResource;
use App\Models\Client;
use App\Models\KnowledgeEntry;
use App\Models\KnowledgeSource;
use App\Models\Skill;
use App\Support\Knowledge\Archive;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mozek a archiv klienta přímo u konverzace s agentem (lišta nad
 * terminálem): co agent o klientovi ví, návrhy ke schválení a dokumenty
 * v archivu, nahrání nového bez odchodu ze stránky. Totéž, co agent
 * dostane na začátku konverzace (KnowledgeBase::opening). Projekt bez
 * klienta (web TAVEO, marketing) ukáže Mozek TAVEO: firemní poznatky,
 * postupy a archiv firmy. Projekt skillu ukáže Mozek skillu: know-how,
 * zkušenosti z klientů a archiv skillu. Viz docs/MOZEK.md.
 */
class ClientBrain extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    /** Null: projekt bez klienta, mozek firmy (nebo skillu). */
    public ?int $clientId = null;

    /** Projekt skillu: jeho know-how, zkušenosti z klientů a archiv. */
    public ?int $skillId = null;

    /** Firemních poznatků je hodně, v panelu jen nejnovější (vždy v hlavě první). */
    private const FIRM_LISTED = 40;

    public function render(): View
    {
        $client = $this->client();
        $skill = $this->skill();
        $entries = $this->entries()
            ->with('client')
            ->whereIn('status', [KnowledgeEntry::STATUS_PROPOSED, KnowledgeEntry::STATUS_APPROVED])
            ->orderByDesc('always')
            ->latest('updated_at')
            ->get();
        $approved = $entries->where('status', KnowledgeEntry::STATUS_APPROVED);

        return view('filament.tools.partials.client-brain', [
            'client' => $client,
            'skill' => $skill,
            'heading' => match (true) {
                $skill !== null => 'Mozek skillu '.$skill->name,
                $client !== null => 'Mozek klienta '.$client->name,
                default => 'Mozek TAVEO',
            },
            'proposed' => $entries->where('status', KnowledgeEntry::STATUS_PROPOSED)->values(),
            // Skill: know-how zvlášť od zkušeností u klientů.
            'approved' => ($skill ? $approved->whereNull('client_id') : $approved)->when(! $client, fn ($approved) => $approved->take(self::FIRM_LISTED))->values(),
            'experience' => $skill ? $approved->whereNotNull('client_id')->take(self::FIRM_LISTED)->values() : collect(),
            'approvedCount' => ($skill ? $approved->whereNull('client_id') : $approved)->count(),
            'experienceCount' => $skill ? $approved->whereNotNull('client_id')->count() : 0,
            'sources' => $this->sources()->with('client')->latest('id')->limit(50)->get(),
            'clientUrl' => $client ? ClientResource::getUrl('edit', ['record' => $client]) : null,
            'skillUrl' => $skill ? SkillResource::getUrl('edit', ['record' => $skill]) : null,
            'entriesUrl' => KnowledgeEntryResource::getUrl('index', match (true) {
                $skill !== null => ['filters' => ['skill' => ['value' => $skill->getKey()]]],
                $client !== null => ['filters' => ['client' => ['value' => $client->getKey()]]],
                default => [],
            }),
        ]);
    }

    public function client(): ?Client
    {
        return $this->clientId ? Client::query()->findOrFail($this->clientId) : null;
    }

    public function skill(): ?Skill
    {
        return $this->skillId ? Skill::query()->findOrFail($this->skillId) : null;
    }

    /** Poznatky klienta, bez klienta poznatky firmy a postupy (bez klienta), u skillu všechny jeho. */
    private function entries(): Builder
    {
        return $this->skillId
            ? KnowledgeEntry::query()->where('skill_id', $this->skillId)
            : KnowledgeEntry::query()->where('client_id', $this->clientId);
    }

    private function sources(): Builder
    {
        return $this->skillId
            ? KnowledgeSource::query()->where('skill_id', $this->skillId)
            : KnowledgeSource::query()->where('client_id', $this->clientId);
    }

    public function approve(int $id): void
    {
        if ($entry = $this->entries()->proposed()->find($id)) {
            KnowledgeEntryResource::approveOrWarn($entry);
        }
    }

    public function reject(int $id): void
    {
        $this->entries()->proposed()->find($id)?->reject(KnowledgeEntryResource::reviewer());
    }

    public function download(int $sourceId, int $index): ?StreamedResponse
    {
        $file = ($this->sources()->find($sourceId)?->files ?? [])[$index] ?? null;

        return is_array($file) && Storage::disk('local')->exists($file['path'] ?? '')
            ? Storage::disk('local')->download($file['path'], $file['name'] ?? basename($file['path']))
            : null;
    }

    public function uploadAction(): Action
    {
        return Action::make('upload')
            ->label('Nahrát do archivu')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('gray')
            ->size('sm')
            ->modalHeading(fn (): string => 'Nahrát do archivu: '.($this->skill() ? 'skill '.$this->skill()->name : ($this->client()?->name ?? 'TAVEO')))
            ->modalDescription(fn (): string => $this->skillId
                ? 'Case studies, výsledky, vzorové weby, poznámky. Knihovník z nich navrhne poznatky skillu a skill je pak použije u všech klientů.'
                : 'Agent dokument najde v této i dalších konverzacích. Když ho potřebujete hned, napište mu, ať se podívá do archivu.')
            ->modalWidth('3xl')
            ->modalSubmitActionLabel('Uložit')
            ->schema(fn (): array => KnowledgeSourceResource::uploadFields(withClient: $this->skillId !== null, withSkill: $this->skillId === null))
            ->action(function (array $data): void {
                $source = app(Archive::class)->store($this->skillId
                    ? [...$data, 'skill_id' => $this->skillId]
                    : [...$data, 'client_id' => $this->clientId], auth()->user()?->name);

                Notification::make()
                    ->title(Archive::storedTitle($source))
                    ->body("V archivu jako #{$source->getKey()}. Agentovi stačí napsat: „podívej se do archivu na #{$source->getKey()}“.")
                    ->status($source->isFailed() ? 'warning' : 'success')
                    ->send();
            });
    }

    /** Odkaz na detail dokumentu (shrnutí knihovníka, návrhy z něj). */
    public function sourceUrl(KnowledgeSource $source): string
    {
        return KnowledgeSourceResource::getUrl('view', ['record' => $source]);
    }
}
