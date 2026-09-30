<?php

namespace App\Enums\Ads;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Otevřené upozornění čeká na reakci. Vzaté na vědomí se dál hlídá,
 * jen nesvítí v souhrnu. Vyřešené zavře buď člověk, nebo samo zmizí,
 * když pravidlo přestane platit. Zamítnuté se týden znovu neotevře.
 */
enum AlertStatus: string implements HasColor, HasLabel
{
    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Otevřené',
            self::Acknowledged => 'Řeší se',
            self::Resolved => 'Vyřešené',
            self::Dismissed => 'Zamítnuté',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'danger',
            self::Acknowledged => 'warning',
            self::Resolved => 'success',
            self::Dismissed => 'gray',
        };
    }

    /** @return list<self> */
    public static function active(): array
    {
        return [self::Open, self::Acknowledged];
    }
}
