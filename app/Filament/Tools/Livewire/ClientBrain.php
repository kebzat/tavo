<?php

namespace App\Filament\Tools\Livewire;

use App\Filament\Tools\Resources\Clients\ClientResource;
use App\Filament\Tools\Resources\KnowledgeEntries\KnowledgeEntryResource;
use App\Filament\Tools\Resources\KnowledgeSources\KnowledgeSourceResource;
use App\Models\Client;
use App\Models\KnowledgeEntry;
use App\Models\KnowledgeSource;
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
 * postupy a archiv firmy. Viz docs/MOZEK.md.
 */
class ClientBrain extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    /** Null: projekt bez klienta, mozek firmy. */
    public ?int $clientId = null;

    /** Firemních poznatků je hodně, v panelu jen nejnovější (vždy v hlavě první). */
    private const FIRM_LISTED = 40;

    public function render(): View
    {
        $client = $this->client();
        $entries = $this->entries()
            ->whereIn('status', [KnowledgeEntry::STATUS_PROPOSED, KnowledgeEntry::STATUS_APPROVED])
            ->orderByDesc('always')
            ->latest('updated_at')
            ->get();

        return view('filament.tools.partials.client-brain', [
            'client' => $client,
            'heading' => $client ? 'Mozek klienta '.$client->name : 'Mozek TAVEO',
            'proposed' => $entries->where('status', KnowledgeEntry::STATUS_PROPOSED)->values(),
            'approved' => $entries->where('status', KnowledgeEntry::STATUS_APPROVED)->when(! $client, fn ($approved) => $approved->take(self::FIRM_LISTED))->values(),
            'approvedCount' => $entries->where('status', KnowledgeEntry::STATUS_APPROVED)->count(),
            'sources' => $this->sources()->latest('id')->limit(50)->get(),
            'clientUrl' => $client ? ClientResource::getUrl('edit', ['record' => $client]) : null,
            'entriesUrl' => KnowledgeEntryResource::getUrl('index', $client ? ['filters' => ['client' => ['value' => $client->getKey()]]] : []),
        ]);
    }

    public function client(): ?Client
    {
        return $this->clientId ? Client::query()->findOrFail($this->clientId) : null;
    }

    /** Poznatky klienta, bez klienta poznatky firmy a postupy (bez klienta). */
    private function entries(): Builder
    {
        return KnowledgeEntry::query()->where('client_id', $this->clientId);
    }

    private function sources(): Builder
    {
        return KnowledgeSource::query()->where('client_id', $this->clientId);
    }

    public function approve(int $id): void
    {
        $this->entries()->proposed()->find($id)?->approve(KnowledgeEntryResource::reviewer());
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
            ->modalHeading(fn (): string => 'Nahrát do archivu: '.($this->client()?->name ?? 'TAVEO'))
            ->modalDescription('Agent dokument najde v této i dalších konverzacích. Když ho potřebujete hned, napište mu, ať se podívá do archivu.')
            ->modalWidth('3xl')
            ->modalSubmitActionLabel('Uložit')
            ->schema(KnowledgeSourceResource::uploadFields(withClient: false))
            ->action(function (array $data): void {
                $source = app(Archive::class)->store([...$data, 'client_id' => $this->clientId], auth()->user()?->name);

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
