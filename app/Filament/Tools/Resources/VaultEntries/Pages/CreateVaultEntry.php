<?php

namespace App\Filament\Tools\Resources\VaultEntries\Pages;

use App\Filament\Tools\Resources\VaultEntries\VaultEntryResource;
use App\Models\VaultEntry;
use App\Support\Vault\VaultLock;
use Filament\Resources\Pages\CreateRecord;

class CreateVaultEntry extends CreateRecord
{
    protected static string $resource = VaultEntryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'created_by' => VaultLock::actor(), 'updated_by' => VaultLock::actor()];
    }

    protected function afterCreate(): void
    {
        /** @var VaultEntry $entry */
        $entry = $this->getRecord();
        $entry->record(VaultLock::actor(), 'created', $entry->agentAccessLabel());
    }

    protected function getRedirectUrl(): string
    {
        return VaultEntryResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
