<?php

namespace App\Enums\Ads;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Nové upozornění čeká na reakci. „Řeším“ a „Čekám na klienta“ se dál
 * hlídají a denně obnovují. Vyřešené zavře buď člověk, nebo samo, když
 * se situace vrátí do normálu (pravidlo přestane platit). Zamítnuté se
 * týden znovu neotevře. Odložení je zvlášť (snoozed_until), stav nemění.
 */
enum AlertStatus: string implements HasColor, HasLabel
{
    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case Waiting = 'waiting';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Nové',
            self::Acknowledged => 'Řeším',
            self::Waiting => 'Čekám na klienta',
            self::Resolved => 'Vyřešeno',
            self::Dismissed => 'Zamítnuto',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'danger',
            self::Acknowledged => 'warning',
            self::Waiting => 'info',
            self::Resolved => 'success',
            self::Dismissed => 'gray',
        };
    }

    /** @return list<self> */
    public static function active(): array
    {
        return [self::Open, self::Acknowledged, self::Waiting];
    }
}
