<?php

namespace App\Enums\Crm;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Jak moc nám na firmě nebo poptávce záleží. Sdílené firmami i poptávkami. */
enum Priority: string implements HasColor, HasLabel
{
    /** Voláme jako první, follow-up hlídáme na dny. */
    case A = 'A';

    /** Standardní tempo. */
    case B = 'B';

    /** Když zbude čas. */
    case C = 'C';

    public function getLabel(): string
    {
        return match ($this) {
            self::A => 'A — přednostně',
            self::B => 'B — běžně',
            self::C => 'C — když zbude čas',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::A => 'danger',
            self::B => 'warning',
            self::C => 'gray',
        };
    }

    /** Zkratka do hustých míst — karta v pipeline, řádek v souhrnu. */
    public function short(): string
    {
        return $this->value;
    }
}
