<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasColor, HasLabel
{
    /** Vidí všechno včetně uživatelů, nastavení a údržby. */
    case Admin = 'admin';

    /** Spravuje jen obsah — reference, služby, stránky, poptávky. */
    case Editor = 'editor';

    /** Klient TAVEO: jen svůj účet (panel `muj-ucet`), žádná administrace ani nástroje. */
    case Client = 'client';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Správce',
            self::Editor => 'Redaktor',
            self::Client => 'Klient',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Admin => 'primary',
            self::Editor => 'gray',
            self::Client => 'info',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Admin => 'Plný přístup — obsah, nastavení webu, uživatelé, údržba.',
            self::Editor => 'Jen obsah — reference, služby, stránky a poptávky.',
            self::Client => 'Jen svůj účet na /muj-ucet (automatizace svého e-shopu).',
        };
    }
}
