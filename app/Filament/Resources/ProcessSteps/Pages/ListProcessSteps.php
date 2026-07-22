<?php

namespace App\Filament\Resources\ProcessSteps\Pages;

use App\Filament\Resources\ProcessSteps\ProcessStepResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProcessSteps extends ListRecords
{
    protected static string $resource = ProcessStepResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
