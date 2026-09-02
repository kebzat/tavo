<?php

namespace App\Enums\Crm;

use Filament\Support\Contracts\HasLabel;

/** Kanál, pro který je šablona napsaná. */
enum TemplateChannel: string implements HasLabel
{
    case Email = 'email';
    case Linkedin = 'linkedin';
    case DemandReply = 'demand_reply';

    /** Osnova hovoru — nikam se neposílá, čte se z obrazovky. */
    case CallScript = 'call_script';

    public function getLabel(): string
    {
        return match ($this) {
            self::Email => 'E-mail',
            self::Linkedin => 'LinkedIn',
            self::DemandReply => 'Odpověď na poptávku',
            self::CallScript => 'Osnova hovoru',
        };
    }

    /** Typ aktivity, který vznikne, když šablonu rovnou zalogujeme. */
    public function toActivityType(): ActivityType
    {
        return match ($this) {
            self::Email => ActivityType::Email,
            self::Linkedin => ActivityType::Linkedin,
            self::DemandReply => ActivityType::DemandReply,
            self::CallScript => ActivityType::Call,
        };
    }

    /** Osnova hovoru nemá předmět, pole by v ní jen překáželo. */
    public function hasSubject(): bool
    {
        return $this !== self::CallScript;
    }
}
