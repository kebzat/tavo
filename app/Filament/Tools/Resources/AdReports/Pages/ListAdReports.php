<?php

namespace App\Filament\Tools\Resources\AdReports\Pages;

use App\Filament\Tools\Resources\AdReports\AdReportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdReports extends ListRecords
{
    protected static string $resource = AdReportResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nový report')];
    }
}
