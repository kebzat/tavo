<?php

namespace App\Enums\Crm;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Jak jsme s poptávkou naložili. */
enum DemandStatus: string implements HasColor, HasLabel
{
    /** Přišla a ještě jsme se na ni nepodívali. */
    case New = 'new';

    case Replied = 'replied';
    case Call = 'call';
    case Proposal = 'proposal';
    case Won = 'won';
    case Lost = 'lost';

    /** Zadavatel si vybral někoho jiného. */
    case ClosedElsewhere = 'closed_elsewhere';

    /** Vědomě jsme nereagovali. */
    case Ignored = 'ignored';

    /**
     * Převod ze stavu v tabulce rešerše. Ta je jen vstupní branou, takže
     * naprostá většina řádků přijde jako „Nový"; ostatní stavy sedí na naše
     * popisky. Neznámý text bereme jako novou poptávku, ať nezapadne.
     */
    public static function fromCsv(?string $value): self
    {
        return match (mb_strtolower(trim((string) $value))) {
            'reagováno', 'reagovano' => self::Replied,
            'hovor' => self::Call,
            'nabídka', 'nabidka' => self::Proposal,
            'vyhráno', 'vyhrano' => self::Won,
            'prohráno', 'prohrano' => self::Lost,
            'zadáno jinam', 'zadano jinam' => self::ClosedElsewhere,
            'nereagujeme', 'ignorováno', 'ignorovano' => self::Ignored,
            default => self::New,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'Nová',
            self::Replied => 'Reagováno',
            self::Call => 'Hovor',
            self::Proposal => 'Nabídka',
            self::Won => 'Vyhráno',
            self::Lost => 'Prohráno',
            self::ClosedElsewhere => 'Zadáno jinam',
            self::Ignored => 'Nereagujeme',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::Replied => 'info',
            self::Call => 'success',
            self::Proposal => 'primary',
            self::Won => 'success',
            self::Lost => 'danger',
            self::ClosedElsewhere => 'gray',
            self::Ignored => 'gray',
        };
    }
}
