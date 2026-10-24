<?php

namespace App\Support\Vault;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Hesla v trezoru jdou zobrazit jen po zadání vlastního hesla do nástrojů,
 * pak 10 minut v tomhle přihlášení. Kdo nechá otevřený počítač, neprozradí
 * trezor celý den.
 */
final class VaultLock
{
    public const MINUTES = 10;

    private const KEY = 'trezor.odemceno_do';

    public static function isUnlocked(): bool
    {
        return (int) session(self::KEY, 0) > now()->timestamp;
    }

    public static function until(): ?Carbon
    {
        return self::isUnlocked() ? Carbon::createFromTimestamp((int) session(self::KEY)) : null;
    }

    public static function unlock(string $password): bool
    {
        $user = Auth::user();

        if (! $user || ! Hash::check($password, $user->getAuthPassword())) {
            return false;
        }

        session([self::KEY => now()->addMinutes(self::MINUTES)->timestamp]);

        return true;
    }

    public static function lock(): void
    {
        session()->forget(self::KEY);
    }

    /** Kdo je u počítače, do historie trezoru. */
    public static function actor(): string
    {
        return (string) (Auth::user()?->name ?? 'neznámý');
    }
}
