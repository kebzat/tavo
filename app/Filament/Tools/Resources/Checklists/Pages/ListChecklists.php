<?php

namespace App\Filament\Tools\Resources\Checklists\Pages;

use App\Filament\Tools\Resources\Checklists\ChecklistResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListChecklists extends ListRecords
{
    protected static string $resource = ChecklistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'klientske' => Tab::make('Klientské')
                ->modifyQueryUsing(fn ($query) => $query->forClients()),

            'sablony' => Tab::make('Šablony')
                ->modifyQueryUsing(fn ($query) => $query->templates()),

            'vse' => Tab::make('Vše'),
        ];
    }
}
