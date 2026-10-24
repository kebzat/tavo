<?php

namespace App\Filament\Tools\Resources\KnowledgeSources\Pages;

use App\Filament\Tools\Resources\KnowledgeSources\KnowledgeSourceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKnowledgeSources extends ListRecords
{
    protected static string $resource = KnowledgeSourceResource::class;

    public function getSubheading(): ?string
    {
        return 'Nasypte sem texty se zkušenostmi. Agent je přečte, roztřídí a navrhne z nich krátké poznatky, vy je jen schválíte. Víc podkladů najednou bere jedny po druhých.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nahrát podklady'),
        ];
    }
}
