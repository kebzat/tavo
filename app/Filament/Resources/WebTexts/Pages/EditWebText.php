<?php

namespace App\Filament\Resources\WebTexts\Pages;

use App\Filament\Resources\WebTexts\WebTextResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWebText extends EditRecord
{
    protected static string $resource = WebTextResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Vrátit původní')
                ->modalHeading('Vrátit původní text?')
                ->modalDescription('Vaše úprava se zahodí a na webu se objeví znění, které je v šabloně.')
                ->modalSubmitActionLabel('Vrátit'),
        ];
    }
}
