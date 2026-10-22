<?php

namespace App\Filament\Tools\Resources\Clients\Pages;

use App\Filament\Tools\Pages\Agent\AgentProject;
use App\Filament\Tools\Resources\Clients\ClientResource;
use App\Models\Client;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
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

            DeleteAction::make(),
        ];
    }
}
