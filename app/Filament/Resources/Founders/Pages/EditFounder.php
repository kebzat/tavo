<?php

namespace App\Filament\Resources\Founders\Pages;

use App\Filament\Resources\Founders\FounderResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFounder extends EditRecord
{
    protected static string $resource = FounderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
