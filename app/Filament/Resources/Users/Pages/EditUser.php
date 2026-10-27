<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\Concerns\CanAuthorizeResourceAccess;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditUser extends EditRecord
{
    use CanAuthorizeResourceAccess;

    protected static string $resource = UserResource::class;

    /** Účet týmu nevisí na klientovi: při změně role z Klienta se klient vymaže. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $role = $data['role'] ?? $this->getRecord()->role;

        if (($role instanceof UserRole ? $role : UserRole::tryFrom((string) $role)) !== UserRole::Client) {
            $data['client_id'] = null;
        }

        return $data;
    }

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
