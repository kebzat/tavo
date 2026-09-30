<?php

namespace App\Filament\Tools\Resources\Clients\RelationManagers;

use App\Filament\Tools\Actions\Ads\ConnectAdAccountAction;
use App\Filament\Tools\Pages\Ads\AdsClient;
use App\Models\Ads\AdAccount;
use App\Models\Client;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

/** Reklamní účty klienta. Vypnutý účet se nestahuje ani nehlídá, data zůstanou. */
class AdAccountsRelationManager extends RelationManager
{
    protected static string $relationship = 'adAccounts';

    protected static ?string $title = 'Reklamní účty';

    public function table(Table $table): Table
    {
        /** @var Client $client */
        $client = $this->getOwnerRecord();

        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Účet')
                    ->weight('bold')
                    ->description(fn (AdAccount $record): string => $record->platform->shortLabel().' · '.$record->external_id.' · '.$record->currency),
                TextColumn::make('status')
                    ->label('Stav')
                    ->badge()
                    ->state(fn (AdAccount $record): string => $record->last_sync_error ? 'Chyba stahování' : $record->statusLabel())
                    ->color(fn (AdAccount $record): string => $record->last_sync_error || ! in_array($record->status, [null, ...AdAccount::OK_STATUSES], true) ? 'danger' : 'success')
                    ->tooltip(fn (AdAccount $record): ?string => $record->last_sync_error),
                TextColumn::make('last_synced_at')->label('Staženo')->since(),
                ToggleColumn::make('is_active')->label('Hlídat'),
            ])
            ->headerActions([
                ConnectAdAccountAction::make($client),
                Action::make('detail')
                    ->label('Výkon klienta')
                    ->icon(Heroicon::OutlinedPresentationChartLine)
                    ->color('gray')
                    ->url(fn (): string => AdsClient::getUrl(['client' => $client->getKey()]))
                    ->visible(fn (): bool => $client->adAccounts()->exists()),
            ])
            ->recordActions([
                Action::make('manager')
                    ->label('Správce reklam')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (AdAccount $record): ?string => $record->managerUrl(), shouldOpenInNewTab: true)
                    ->visible(fn (AdAccount $record): bool => $record->managerUrl() !== null),
                DeleteAction::make()
                    ->label('Odpojit')
                    ->modalDescription('Smaže účet i všechna stažená čísla a kampaně. Když ho chcete jen přestat hlídat, vypněte přepínač.'),
            ])
            ->emptyStateHeading('Žádný reklamní účet')
            ->emptyStateDescription('Klient nasdílí účet Business Manageru Taveo jako partnerovi a pak ho tu propojíte.');
    }
}
