<?php

namespace App\Enums\Ads;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ReportStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Sent = 'sent';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Koncept',
            self::Sent => 'Odesláno',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Sent => 'success',
        };
    }
}
