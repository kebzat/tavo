<?php

namespace App\Filament\Tools\Resources\Demands\Pages;

use App\Filament\Tools\Resources\Demands\DemandResource;
use App\Models\Crm\Demand;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditDemand extends EditRecord
{
    protected static string $resource = DemandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openDemand')
                ->label('Otevřít na portálu')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (Demand $record): string => $record->url, shouldOpenInNewTab: true),

            DeleteAction::make(),
        ];
    }
}
