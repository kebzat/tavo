<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ChecklistPriority: string implements HasColor, HasLabel
{
    /** Bez tohohle web spouštět nemá smysl. */
    case Must = 'must';

    /** Doporučujeme, ale start to nezastaví. */
    case Should = 'should';

    /** Hezké mít, řeší se, až zbude čas. */
    case Nice = 'nice';

    public function getLabel(): string
    {
        return match ($this) {
            self::Must => 'Nutnost',
            self::Should => 'Doporučeno',
            self::Nice => 'Volitelné',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Must => 'danger',
            self::Should => 'warning',
            self::Nice => 'gray',
        };
    }

    /**
     * Štítek na sdílené stránce. Celé literály tříd — skládané názvy
     * Tailwind scanner nenajde a do CSS by se nedostaly.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Must => 'bg-brick/12 text-brick',
            self::Should => 'bg-ink/8 text-body',
            self::Nice => 'bg-ink/5 text-muted',
        };
    }
}
