<?php

namespace App\Filament\Resources\EshopOffers\Pages;

use App\Filament\Resources\EshopOffers\EshopOfferResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEshopOffers extends ListRecords
{
    protected static string $resource = EshopOfferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
