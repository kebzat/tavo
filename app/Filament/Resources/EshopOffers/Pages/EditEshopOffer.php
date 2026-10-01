<?php

namespace App\Filament\Resources\EshopOffers\Pages;

use App\Filament\Resources\EshopOffers\EshopOfferResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEshopOffer extends EditRecord
{
    protected static string $resource = EshopOfferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('showOnWeb')
                ->label('Zobrazit na webu')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn () => $this->record->url())
                ->openUrlInNewTab(),
            DeleteAction::make(),
        ];
    }
}
