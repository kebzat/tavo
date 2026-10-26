<?php

namespace App\Filament\Tools\Resources\Clients\Pages;

use App\Filament\Tools\Pages\Agent\AgentProject;
use App\Filament\Tools\Resources\Clients\ClientResource;
use App\Models\Client;
use App\Support\ClickUp\ClientImport;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('agent')
                ->label('Agent')
                ->icon(Heroicon::OutlinedCommandLine)
                ->color('gray')
                ->url(fn (Client $record): string => AgentProject::getUrl(['project' => $record->agent_project]))
                ->visible(fn (Client $record): bool => filled($record->agent_project)),

            Action::make('clickup')
                ->label('Načíst z ClickUpu znovu')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible(fn (Client $record): bool => filled($record->clickup_folder_id))
                ->disabled(fn (Client $record): bool => in_array($record->clickup_import['status'] ?? null, [ClientImport::STATUS_QUEUED, ClientImport::STATUS_RUNNING], true))
                ->requiresConfirmation()
                ->modalHeading('Načíst složku z ClickUpu znovu?')
                ->modalDescription('Dokumenty z ClickUpu v archivu se přepíšou podle toho, co je teď ve složce, nové seznamy a Docs přibudou. Ostatní dokumenty v archivu zůstanou.')
                ->action(function (Client $record): void {
                    app(ClientImport::class)->queue($record, auth()->user()?->name);
                    Notification::make()->success()->title('Načítá se na pozadí')->body('Průběh je v Archivu klienta.')->send();
                }),

            DeleteAction::make(),
        ];
    }
}
