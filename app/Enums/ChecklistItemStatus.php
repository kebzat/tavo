<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ChecklistItemStatus: string implements HasColor, HasLabel
{
    /** Výchozí stav po naklonování ze šablony. */
    case Todo = 'todo';

    /** Pracuje se na tom. */
    case InProgress = 'in_progress';

    /** Hotovo a ověřeno. */
    case Done = 'done';

    /** Vědomě se neřeší — u tohohle klienta nedává smysl. */
    case Skipped = 'skipped';

    public function getLabel(): string
    {
        return match ($this) {
            self::Todo => 'Čeká',
            self::InProgress => 'Probíhá',
            self::Done => 'Hotovo',
            self::Skipped => 'Neřeší se',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Todo => 'gray',
            self::InProgress => 'info',
            self::Done => 'success',
            self::Skipped => 'warning',
        };
    }

    /**
     * Vyřízené položky. Přeskočené se počítají taky — do progresu se promítnou
     * jako vyřešené, protože už nic nebrání spuštění.
     */
    public function isFinished(): bool
    {
        return $this === self::Done || $this === self::Skipped;
    }

    /*
    |--------------------------------------------------------------------------
    | Vzhled na sdílené stránce
    |--------------------------------------------------------------------------
    | getColor() výš mluví jazykem Filamentu (success, danger). Web má vlastní
    | paletu, takže si stavy překládají třídy zvlášť. Vždy celé literály —
    | skládané názvy tříd Tailwind scanner nenajde.
    */

    /** Kolečko před položkou. */
    public function markerClasses(): string
    {
        return match ($this) {
            self::Done => 'border-brick bg-brick text-cream',
            self::InProgress => 'border-brick bg-transparent text-brick',
            self::Skipped => 'border-ink/15 bg-transparent text-muted',
            self::Todo => 'border-ink/25 bg-transparent text-transparent',
        };
    }

    /** Znak uvnitř kolečka. Prázdný u nezačatých položek. */
    public function markerGlyph(): string
    {
        return match ($this) {
            self::Done => '✓',
            self::InProgress => '•',
            self::Skipped => '–',
            self::Todo => '',
        };
    }

    /** Vyřízené položky ztlumíme, ať vyniknou ty zbývající. */
    public function titleClasses(): string
    {
        return match ($this) {
            self::Done => 'text-muted',
            self::Skipped => 'text-muted line-through',
            self::InProgress, self::Todo => 'text-ink',
        };
    }
}
