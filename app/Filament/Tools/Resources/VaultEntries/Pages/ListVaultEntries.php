<?php

namespace App\Filament\Tools\Resources\VaultEntries\Pages;

use App\Filament\Tools\Resources\VaultEntries\VaultEntryResource;
use App\Support\Vault\VaultLock;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListVaultEntries extends ListRecords
{
    protected static string $resource = VaultEntryResource::class;

    public function getSubheading(): ?string
    {
        return 'Hesla jsou zašifrovaná. Zobrazíš je po zadání svého hesla, agent je nedostane nikdy: práci s nimi za něj dělá web podle „Co smí agent“.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('lock')
                ->label(fn (): string => 'Zamknout (odemčeno do '.VaultLock::until()?->timezone('Europe/Prague')->format('G:i').')')
                ->icon(Heroicon::OutlinedLockClosed)
                ->color('gray')
                ->visible(fn (): bool => VaultLock::isUnlocked())
                ->action(fn () => VaultLock::lock()),
            CreateAction::make()->label('Přidat přístup')->icon(Heroicon::OutlinedPlus),
        ];
    }
}
