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
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mozek a archiv klienta přímo u konverzace s agentem (lišta nad
 * terminálem): co agent o klientovi ví, návrhy ke schválení a dokumenty
 * v archivu, nahrání nového bez odchodu ze stránky. Totéž, co agent
 * dostane na začátku konverzace (KnowledgeBase::opening). Viz docs/MOZEK.md.
 */
class ClientBrain extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public int $clientId;

    public function render(): View
    {
        $client = $this->client();
        $entries = $client->knowledgeEntries()
            ->whereIn('status', [KnowledgeEntry::STATUS_PROPOSED, KnowledgeEntry::STATUS_APPROVED])
            ->latest('updated_at')
            ->get();

        return view('filament.tools.partials.client-brain', [
            'client' => $client,
            'proposed' => $entries->where('status', KnowledgeEntry::STATUS_PROPOSED)->values(),
            'approved' => $entries->where('status', KnowledgeEntry::STATUS_APPROVED)->values(),
            'sources' => $client->knowledgeSources()->latest('id')->limit(50)->get(),
            'clientUrl' => ClientResource::getUrl('edit', ['record' => $client]),
            'entriesUrl' => KnowledgeEntryResource::getUrl('index', ['filters' => ['client' => ['value' => $client->getKey()]]]),
        ]);
    }

    public function client(): Client
    {
        return Client::query()->findOrFail($this->clientId);
    }

    public function approve(int $id): void
    {
        $this->client()->knowledgeEntries()->proposed()->find($id)?->approve(KnowledgeEntryResource::reviewer());
    }

    public function reject(int $id): void
    {
        $this->client()->knowledgeEntries()->proposed()->find($id)?->reject(KnowledgeEntryResource::reviewer());
    }

    public function download(int $sourceId, int $index): ?StreamedResponse
    {
        $file = ($this->client()->knowledgeSources()->find($sourceId)?->files ?? [])[$index] ?? null;

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
            ->modalHeading(fn (): string => 'Nahrát do archivu: '.$this->client()->name)
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
