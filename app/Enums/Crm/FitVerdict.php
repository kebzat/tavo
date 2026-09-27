<?php

namespace App\Enums\Crm;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Jak firma sedí na to, co Taveo prodává. Vychází ze skóre proklepnutí webu. */
enum FitVerdict: string implements HasColor, HasLabel
{
    /** Zavedený web s rozpočtem a věcmi k opravě. Oslovit. */
    case Strong = 'strong';

    /** Něco tam je, ale chce to lidský pohled. */
    case Maybe = 'maybe';

    /** Malý nebo opuštěný web bez známek rozpočtu. */
    case Poor = 'poor';

    /** Agentura nebo marketér. Neprodáváme jim web, ale vývojovou kapacitu. */
    case Partner = 'partner';

    /** Web nejde načíst. Buď leží, nebo firma skončila. */
    case Unreachable = 'unreachable';

    public function getLabel(): string
    {
        return match ($this) {
            self::Strong => 'Silný kandidát',
            self::Maybe => 'Zvážit',
            self::Poor => 'Nehodí se',
            self::Partner => 'Partner',
            self::Unreachable => 'Web nejde načíst',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Strong => 'success',
            self::Maybe => 'warning',
            self::Poor => 'gray',
            self::Partner => 'info',
            self::Unreachable => 'danger',
        };
    }

    public static function fromScore(int $score): self
    {
        return match (true) {
            $score >= 60 => self::Strong,
            $score >= 40 => self::Maybe,
            default => self::Poor,
        };
    }

    /** Verdikty, u kterých se firma z rešerše smí odložit automaticky. */
    public function isRejected(): bool
    {
        return in_array($this, [self::Poor, self::Unreachable], true);
    }
}
