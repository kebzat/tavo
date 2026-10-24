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

    protected static ?string $title = 'Nahrát podklady do mozku';

    protected static bool $canCreateAnother = false;

    /** Cesty a jména souborů z formuláře → jeden seznam. */
    protected function handleRecordCreation(array $data): Model
    {
        $names = $data['file_names'] ?? [];
        $source = KnowledgeSource::create([
            'title' => $data['title'],
            'client_id' => $data['client_id'] ?? null,
            'body' => filled($data['body'] ?? null) ? $data['body'] : null,
            'files' => collect($data['file_paths'] ?? [])->values()->map(fn (string $path): array => ['path' => $path, 'name' => $names[$path] ?? basename($path)])->all() ?: null,
            'note' => $data['note'] ?? null,
            'author' => auth()->user()?->name,
        ]);

        app(BrainAgent::class)->process($source);

        return $source->refresh();
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Poslat agentovi');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return $this->getRecord()->isFailed()
            ? 'Podklady jsou uložené, ale agenta se nepodařilo spustit'
            : 'Agent podklady čte';
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
