<?php

namespace App\Filament\Tools\Resources\Audits\Pages;

use App\Filament\Tools\Resources\Audits\AuditResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAudits extends ListRecords
{
    protected static string $resource = AuditResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
