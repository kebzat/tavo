<?php

namespace App\Filament\Tools\Resources\KnowledgeSources\Pages;

use App\Filament\Tools\Resources\KnowledgeSources\KnowledgeSourceResource;
use App\Support\Knowledge\Archive;
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

    protected function handleRecordCreation(array $data): Model
    {
        return app(Archive::class)->store($data, auth()->user()?->name);
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Uložit');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return Archive::storedTitle($this->getRecord());
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
