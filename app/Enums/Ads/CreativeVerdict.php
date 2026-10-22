<?php

namespace App\Enums\Ads;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Výsledek kreativního testu. „Málo dat“ není neúspěch: test se nedá
 * vyhodnotit a nápad se smí zkusit znovu s větším rozpočtem.
 */
enum CreativeVerdict: string implements HasColor, HasLabel
{
    case Running = 'running';
    case Works = 'works';
    case DoesNotWork = 'does_not_work';
    case NotEnoughData = 'not_enough_data';

    public function getLabel(): string
    {
        return match ($this) {
            self::Running => 'Běží',
            self::Works => 'Funguje',
            self::DoesNotWork => 'Nefunguje',
            self::NotEnoughData => 'Málo dat',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Running => 'info',
            self::Works => 'success',
            self::DoesNotWork => 'danger',
            self::NotEnoughData => 'gray',
        };
    }
}
