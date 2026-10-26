<?php

namespace App\Filament\Tools\Resources\VaultEntries\Pages;

use App\Filament\Tools\Resources\VaultEntries\VaultEntryResource;
use App\Models\VaultEntry;
use App\Support\Vault\VaultLock;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Uložená hesla se do formuláře nenačítají (neodejdou do prohlížeče).
 * Prázdné pole při uložení znamená „beze změny“, zobrazit je jde tlačítkem Zobrazit.
 */
class EditVaultEntry extends EditRecord
{
    protected static string $resource = VaultEntryResource::class;

    private const SECRETS = ['secret', 'app_password', 'totp_secret'];

    /** @var list<string> která hesla se při uložení změnila (do historie) */
    private array $changedSecrets = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, ...array_fill_keys(self::SECRETS, null), 'notes' => $this->getRecord()->notes];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->changedSecrets = array_keys(array_filter(array_intersect_key($data, array_flip(self::SECRETS)), 'filled'));

        return [...$data, 'updated_by' => VaultLock::actor()];
    }

    protected function afterSave(): void
    {
        /** @var VaultEntry $entry */
        $entry = $this->getRecord();
        $labels = ['secret' => 'heslo', 'app_password' => 'aplikační heslo', 'totp_secret' => 'klíč 2FA'];
        $entry->record(VaultLock::actor(), 'updated', implode(', ', array_filter([
            $this->changedSecrets ? 'nové '.implode(', ', array_map(fn (string $key): string => $labels[$key], $this->changedSecrets)) : null,
            'agent: '.$entry->agentAccessLabel(),
        ])));
    }

    protected function getHeaderActions(): array
    {
        return [
            VaultEntryResource::revealAction()->label('Zobrazit heslo'),
            VaultEntryResource::testAction(),
            DeleteAction::make()
                ->modalDescription('Přístup zmizí z trezoru i agentovi. Historie použití zůstane. Heslo u služby tím nezrušíš, to udělej tam.')
                ->before(fn (VaultEntry $record) => $record->record(VaultLock::actor(), 'deleted')),
        ];
    }
}
