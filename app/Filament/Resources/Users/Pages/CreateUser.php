<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\Concerns\CanAuthorizeResourceAccess;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    use CanAuthorizeResourceAccess;

    protected static string $resource = UserResource::class;
}
