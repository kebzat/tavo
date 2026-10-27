<?php

namespace App\Filament\Tools\Resources\Automations\Pages;

use App\Filament\Tools\Resources\Automations\AutomationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAutomation extends CreateRecord
{
    protected static string $resource = AutomationResource::class;

    public function getTitle(): string
    {
        return 'Nová automatizace';
    }

    /** Po založení rovnou na úpravu, tam je adresa CSV pro Upgates. */
    protected function getRedirectUrl(): string
    {
        return AutomationResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
