<?php

namespace App\Filament\Tools\Resources\AdGraphics\Pages;

use App\Filament\Tools\Pages\Ads\ManageAdGraphics;
use App\Filament\Tools\Resources\AdGraphics\AdGraphicResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListAdGraphics extends ListRecords
{
    protected static string $resource = AdGraphicResource::class;

    public function getSubheading(): ?string
    {
        return 'Agent projde web klienta a připraví návrhy reklam jako obrázky a ve Figmě, kde jdou dál upravit. Trvá to zhruba 10–30 minut.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('settings')
                ->label('Nastavení')
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->color('gray')
                ->url(ManageAdGraphics::getUrl()),
            CreateAction::make()->label('Nová grafika'),
        ];
    }
}
