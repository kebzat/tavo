<?php

namespace App\Enums\Ads;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AlertSeverity: string implements HasColor, HasLabel
{
    case Critical = 'critical';
    case Warning = 'warning';
    case Info = 'info';

    public function getLabel(): string
    {
        return match ($this) {
            self::Critical => 'Hoří',
            self::Warning => 'Pozor',
            self::Info => 'Tip',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Critical => 'danger',
            self::Warning => 'warning',
            self::Info => 'info',
        };
    }

    /** Pořadí při řazení, nejvážnější nahoře. */
    public function weight(): int
    {
        return match ($this) {
            self::Critical => 3,
            self::Warning => 2,
            self::Info => 1,
        };
    }
}
