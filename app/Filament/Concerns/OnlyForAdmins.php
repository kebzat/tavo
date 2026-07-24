<?php

namespace App\Filament\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * Schová resource nebo stránku redaktorům — z navigace i z routy,
 * takže se tam nedostanou ani přímým odkazem.
 */
trait OnlyForAdmins
{
    public static function canAccess(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }
}
