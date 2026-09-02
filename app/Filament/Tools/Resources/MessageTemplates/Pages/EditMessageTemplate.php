<?php

namespace App\Filament\Tools\Resources\MessageTemplates\Pages;

use App\Filament\Tools\Resources\MessageTemplates\MessageTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMessageTemplate extends EditRecord
{
    protected static string $resource = MessageTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
