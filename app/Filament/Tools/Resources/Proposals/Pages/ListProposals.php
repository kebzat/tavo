<?php

namespace App\Filament\Tools\Resources\Proposals\Pages;

use App\Filament\Tools\Resources\Proposals\ProposalResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProposals extends ListRecords
{
    protected static string $resource = ProposalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
