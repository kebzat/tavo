<?php

namespace App\Filament\Client\Pages\Auth;

use App\Enums\UserRole;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Password;

/**
 * Zapomenuté heslo v účtu klienta jen pro účty s rolí Klient. Účty týmu
 * (nástroje a administrace) si heslo e-mailem obnovit nemůžou, ať tahle
 * veřejná stránka není nová cesta k nim. Pro jiný e-mail odpověď vypadá
 * stejně (nic neprozradí).
 */
class RequestPasswordReset extends BaseRequestPasswordReset
{
    protected function getCredentialsFromFormData(array $data): array
    {
        return [...parent::getCredentialsFromFormData($data), 'role' => UserRole::Client->value];
    }

    /** Neznámý e-mail dostane stejnou odpověď jako známý, ať se nedá zjistit, kdo má účet. */
    protected function getFailureNotification(string $status): ?Notification
    {
        return $status === Password::INVALID_USER
            ? $this->getSentNotification(Password::RESET_LINK_SENT)
            : parent::getFailureNotification($status);
    }
}
