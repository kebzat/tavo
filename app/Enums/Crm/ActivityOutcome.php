<?php

namespace App\Enums\Crm;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Jak aktivita dopadla. Prázdné u poznámek a úkolů. */
enum ActivityOutcome: string implements HasColor, HasLabel
{
    case NoAnswer = 'no_answer';
    case Positive = 'positive';
    case Neutral = 'neutral';
    case Negative = 'negative';

    public function getLabel(): string
    {
        return match ($this) {
            self::NoAnswer => 'Nedovoláno / bez reakce',
            self::Positive => 'Zájem',
            self::Neutral => 'Neutrální',
            self::Negative => 'Odmítnutí',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NoAnswer => 'gray',
            self::Positive => 'success',
            self::Neutral => 'info',
            self::Negative => 'danger',
        };
    }

    /**
     * Výsledky, které bereme jako odpověď protistrany. Nedovoláno se
     * nepočítá — nikdo nám neodpověděl.
     *
     * @return array<int, self>
     */
    public static function answered(): array
    {
        return [self::Positive, self::Neutral];
    }
}
