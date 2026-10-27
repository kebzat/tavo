<?php

namespace App\Filament\Tools\Resources\Automations\Pages;

use App\Filament\Tools\Resources\Automations\AutomationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListAutomations extends ListRecords
{
    protected static string $resource = AutomationResource::class;

    public function getSubheading(): ?string
    {
        return 'Co za klienty dělá web sám každou hodinu. Klient vidí přehled a přepínače ve svém účtu (/muj-ucet).';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Přidat automatizaci')->icon(Heroicon::OutlinedPlus),
        ];
    }
}
