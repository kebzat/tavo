<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\Concerns\CanAuthorizeResourceAccess;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditUser extends EditRecord
{
    use CanAuthorizeResourceAccess;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // DeleteAction se neptá resource na canDelete(), takže omezení
            // musí být tady. Tvrdou pojistku má navíc i model.
            DeleteAction::make()
                ->visible(fn (): bool => $this->getRecord()->getKey() !== Auth::id()),
        ];
    }
}
