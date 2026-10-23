<?php

namespace App\Enums\Ads;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Koncept čeká na kontrolu. Schválený má čísla zamčená: po aktualizaci
 * dat z Mety se tiše nezmění, i kdyby se odeslal až za týden.
 */
enum ReportStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Sent = 'sent';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Ke kontrole',
            self::Approved => 'Schváleno',
            self::Sent => 'Odesláno',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'warning',
            self::Approved => 'info',
            self::Sent => 'success',
        };
    }
}
