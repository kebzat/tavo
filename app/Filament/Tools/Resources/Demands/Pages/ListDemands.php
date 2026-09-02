<?php

namespace App\Filament\Tools\Resources\Demands\Pages;

use App\Filament\Tools\Resources\Demands\DemandResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDemands extends ListRecords
{
    protected static string $resource = DemandResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Přidat poptávku')];
    }
}
