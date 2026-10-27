<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Druh automatizace klienta. Viz docs/AUTOMATIZACE.md. */
enum AutomationType: string implements HasLabel
{
    /** Upgates + Zaslat: připomínky vyzvednutí, poslední den a vrácené zásilky. */
    case UpgatesParcelPickup = 'upgates_parcel_pickup';

    public function getLabel(): string
    {
        return match ($this) {
            self::UpgatesParcelPickup => 'Připomínky vyzvednutí zásilek',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::UpgatesParcelPickup => 'Když zásilka leží na výdejním místě, e-shop zákazníkovi připomene vyzvednutí, poslední den pošle SMS a po vrácení e-mail.',
        };
    }
}
