<?php

namespace App\Filament\Tools\Resources\Checklists\Pages;

use App\Filament\Tools\Resources\Checklists\ChecklistResource;
use Filament\Resources\Pages\CreateRecord;

class CreateChecklist extends CreateRecord
{
    protected static string $resource = ChecklistResource::class;
}
