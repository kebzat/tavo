<?php

namespace App\Filament\Tools\Resources\Audits\Pages;

use App\Filament\Tools\Resources\Audits\AuditResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAudit extends CreateRecord
{
    protected static string $resource = AuditResource::class;
}
