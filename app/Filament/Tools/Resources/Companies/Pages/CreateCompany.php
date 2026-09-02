<?php

namespace App\Filament\Tools\Resources\Companies\Pages;

use App\Filament\Tools\Resources\Companies\CompanyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCompany extends CreateRecord
{
    protected static string $resource = CompanyResource::class;

    /** Po založení se pokračuje na kartě — tam se firma dál zpracovává. */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
