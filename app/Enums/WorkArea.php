<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Oblast práce u klienta. Podle ní se dělí paušál i odpracované hodiny. */
enum WorkArea: string implements HasColor, HasLabel
{
    case Web = 'web';

    case Marketing = 'marketing';

    public function getLabel(): string
    {
        return match ($this) {
            self::Web => 'Vývoj webu',
            self::Marketing => 'Marketing',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Web => 'info',
            self::Marketing => 'success',
        };
    }

    /** Štítek a sloupec grafu na přehledu pro klienta. Celé literály kvůli Tailwind scanneru. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Web => 'bg-ink/8 text-ink',
            self::Marketing => 'bg-moss/14 text-moss',
        };
    }

    public function barClasses(): string
    {
        return match ($this) {
            self::Web => 'bg-ink',
            self::Marketing => 'bg-moss',
        };
    }
}
