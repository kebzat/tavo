<?php

namespace App\Filament\Tools\Resources\AdEvents\Pages;

use App\Filament\Tools\Actions\Ads\AdEventForm;
use App\Filament\Tools\Resources\AdEvents\AdEventResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Model;

class ManageAdEvents extends ManageRecords
{
    protected static string $resource = AdEventResource::class;

    public function getSubheading(): ?string
    {
        return 'Upozorníme předem v ranním přehledu i e-mailu. Nevyřešený termín tam visí i po datu, dokud ho neoznačíte. Reklamy vypínáte ručně.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Přidat termín')
                ->modalHeading('Nový termín')
                ->modalWidth('2xl')
                ->using(fn (array $data): Model => AdEventForm::save($data)),
        ];
    }
}
