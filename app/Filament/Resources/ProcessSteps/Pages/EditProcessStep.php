<?php

namespace App\Filament\Resources\ProcessSteps\Pages;

use App\Filament\Resources\ProcessSteps\ProcessStepResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProcessStep extends EditRecord
{
    protected static string $resource = ProcessStepResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
