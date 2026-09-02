<?php

namespace App\Filament\Tools\Resources\Companies\Pages;

use App\Filament\Tools\Resources\Companies\CompanyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCompanies extends ListRecords
{
    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Přidat firmu'),
        ];
    }
}
