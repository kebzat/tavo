<?php

namespace App\Filament\Tools\Resources\Deals\Pages;

use App\Filament\Tools\Resources\Companies\CompanyResource;
use App\Filament\Tools\Resources\Deals\DealResource;
use App\Models\Crm\Deal;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditDeal extends EditRecord
{
    protected static string $resource = DealResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Z obchodu se skoro vždy pokračuje na kartu firmy — tam je
            // historie i kontakty.
            Action::make('openCompany')
                ->label('Karta firmy')
                ->icon(Heroicon::OutlinedBuildingStorefront)
                ->color('gray')
                ->url(fn (Deal $record): string => CompanyResource::getUrl('edit', ['record' => $record->company_id])),

            DeleteAction::make(),
        ];
    }
}
