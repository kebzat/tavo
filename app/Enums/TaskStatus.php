<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TaskStatus: string implements HasColor, HasLabel
{
    case Planned = 'planned';

    case InProgress = 'in_progress';

    /** Stojí na klientovi: podklady, přístupy, schválení. */
    case Waiting = 'waiting';

    case Done = 'done';

    public function getLabel(): string
    {
        return match ($this) {
            self::Planned => 'V plánu',
            self::InProgress => 'Rozpracováno',
            self::Waiting => 'Čeká na klienta',
            self::Done => 'Hotovo',
        };
    }

    /** Znění na přehledu pro klienta, oslovuje ho přímo. */
    public function clientLabel(): string
    {
        return match ($this) {
            self::Waiting => 'Čeká na vás',
            default => $this->getLabel(),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Planned => 'gray',
            self::InProgress => 'warning',
            self::Waiting => 'danger',
            self::Done => 'success',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Planned => 'bg-ink/5 text-muted',
            self::InProgress => 'bg-ink/8 text-body',
            self::Waiting => 'bg-brick/12 text-brick',
            self::Done => 'bg-moss/14 text-moss',
        };
    }

    public function isOpen(): bool
    {
        return $this !== self::Done;
    }
}
