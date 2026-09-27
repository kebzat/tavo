<?php

namespace App\Filament\Resources\ClientLogos\Pages;

use App\Filament\Resources\ClientLogos\ClientLogoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListClientLogos extends ListRecords
{
    protected static string $resource = ClientLogoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
