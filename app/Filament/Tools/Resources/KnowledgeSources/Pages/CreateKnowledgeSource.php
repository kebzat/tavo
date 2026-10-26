<?php

namespace App\Filament\Tools\Resources\KnowledgeSources\Pages;

use App\Filament\Tools\Resources\KnowledgeSources\KnowledgeSourceResource;
use App\Models\KnowledgeSource;
use App\Support\Knowledge\BrainAgent;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateKnowledgeSource extends CreateRecord
{
    protected static string $resource = KnowledgeSourceResource::class;

    protected static ?string $title = 'Nahrát do archivu a mozku';

    protected static bool $canCreateAnother = false;

    /** Z karty klienta (Archiv → Nahrát) je klient předvyplněný. */
    protected function fillForm(): void
    {
        parent::fillForm();

        if (is_numeric(request()->query('klient'))) {
            $this->form->fill([...$this->form->getRawState(), 'client_id' => (int) request()->query('klient')]);
        }
    }

    /** Cesty a jména souborů z formuláře → jeden seznam. */
    protected function handleRecordCreation(array $data): Model
    {
        $names = $data['file_names'] ?? [];
        $learn = (bool) ($data['learn'] ?? true);
        $source = KnowledgeSource::create([
            'title' => $data['title'],
            'client_id' => $data['client_id'] ?? null,
            'category' => $data['category'] ?? null,
            'document_date' => $data['document_date'] ?? null,
            'learn' => $learn,
            'status' => $learn ? KnowledgeSource::STATUS_QUEUED : KnowledgeSource::STATUS_STORED,
            'body' => filled($data['body'] ?? null) ? $data['body'] : null,
            'files' => collect($data['file_paths'] ?? [])->values()->map(fn (string $path): array => ['path' => $path, 'name' => $names[$path] ?? basename($path)])->all() ?: null,
            'note' => $data['note'] ?? null,
            'author' => auth()->user()?->name,
        ]);

        if ($learn) {
            app(BrainAgent::class)->process($source);
        }

        return $source->refresh();
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Uložit');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return match (true) {
            $this->getRecord()->isFailed() => 'Podklady jsou uložené, ale agenta se nepodařilo spustit',
            $this->getRecord()->isStored() => 'Uloženo do archivu',
            default => 'Agent podklady čte',
        };
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
