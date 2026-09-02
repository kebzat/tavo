<?php

namespace App\Enums\Crm;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Kde ve vztahu s firmou právě jsme. */
enum CompanyStatus: string implements HasColor, HasLabel
{
    /** Vyhledaná firma, ještě jsme se neozvali. */
    case New = 'new';

    /** Poslali jsme první zprávu. */
    case Contacted = 'contacted';

    /** Čekáme a máme naplánovanou připomínku. */
    case FollowUp = 'follow_up';

    /** Odepsali. */
    case Replied = 'replied';

    /** Domluvený nebo proběhlý hovor. */
    case Call = 'call';

    /** Nabídka je venku. */
    case Proposal = 'proposal';

    case Won = 'won';
    case Lost = 'lost';

    /** Vědomě odloženo — teď to nemá smysl, vrátíme se později. */
    case Parked = 'parked';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'Nová',
            self::Contacted => 'Osloveno',
            self::FollowUp => 'Follow-up',
            self::Replied => 'Odpověděli',
            self::Call => 'Hovor',
            self::Proposal => 'Nabídka',
            self::Won => 'Vyhráno',
            self::Lost => 'Prohráno',
            self::Parked => 'Odloženo',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'gray',
            self::Contacted => 'info',
            self::FollowUp => 'warning',
            self::Replied => 'success',
            self::Call => 'success',
            self::Proposal => 'primary',
            self::Won => 'success',
            self::Lost => 'danger',
            self::Parked => 'gray',
        };
    }

    /**
     * Stavy, u kterých čekáme pohyb. Firmy, které v nich leží bez aktivity,
     * hlásí přehled „Dnes" jako spící.
     *
     * @return array<int, self>
     */
    public static function active(): array
    {
        return [self::Contacted, self::FollowUp, self::Replied];
    }

    /** Uzavřené případy se do pracovních přehledů nepočítají. */
    public function isClosed(): bool
    {
        return in_array($this, [self::Won, self::Lost, self::Parked], true);
    }
}
