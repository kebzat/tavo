<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\Concerns\CanAuthorizeResourceAccess;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    // ListRecords sám nic neautorizuje — bez tohohle by se redaktor
    // na výpis uživatelů dostal přímou adresou, jen by ho neviděl v menu.
    use CanAuthorizeResourceAccess;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
