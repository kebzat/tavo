<?php

namespace App\Filament\Resources\ClientLogos\Pages;

use App\Filament\Resources\ClientLogos\ClientLogoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditClientLogo extends EditRecord
{
    protected static string $resource = ClientLogoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
