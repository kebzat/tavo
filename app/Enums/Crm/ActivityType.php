<?php

namespace App\Enums\Crm;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/** Co jsme udělali. Podle typu se počítají týdenní KPI. */
enum ActivityType: string implements HasColor, HasLabel
{
    case Email = 'email';
    case Call = 'call';
    case Meeting = 'meeting';
    case Linkedin = 'linkedin';

    /** Reakce na poptávku z portálu. */
    case DemandReply = 'demand_reply';

    /** Poznámka bez kontaktu — co jsme si o firmě zjistili. */
    case Note = 'note';

    /** Úkol na sebe. */
    case Task = 'task';

    public function getLabel(): string
    {
        return match ($this) {
            self::Email => 'E-mail',
            self::Call => 'Hovor',
            self::Meeting => 'Schůzka',
            self::Linkedin => 'LinkedIn',
            self::DemandReply => 'Reakce na poptávku',
            self::Note => 'Poznámka',
            self::Task => 'Úkol',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Email => 'info',
            self::Call => 'success',
            self::Meeting => 'success',
            self::Linkedin => 'primary',
            self::DemandReply => 'warning',
            self::Note => 'gray',
            self::Task => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Email => Heroicon::OutlinedEnvelope,
            self::Call => Heroicon::OutlinedPhone,
            self::Meeting => Heroicon::OutlinedUsers,
            self::Linkedin => Heroicon::OutlinedGlobeAlt,
            self::DemandReply => Heroicon::OutlinedInboxArrowDown,
            self::Note => Heroicon::OutlinedPencilSquare,
            self::Task => Heroicon::OutlinedCheckCircle,
        };
    }

    /**
     * Typy, kterými jsme firmu oslovili. Posouvají firmu z „Nová" na
     * „Osloveno" a počítají se do KPI jako oslovení či follow-up.
     *
     * @return array<int, self>
     */
    public static function outreach(): array
    {
        return [self::Email, self::Call, self::Linkedin, self::DemandReply, self::Meeting];
    }

    public function isOutreach(): bool
    {
        return in_array($this, self::outreach(), true);
    }
}
