<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Kudy jde hotová práce agenta na web po tlačítku Zveřejnit.
 * Viz App\Support\Agent\Publisher.
 */
enum PublicationStatus: string implements HasColor, HasLabel
{
    /** Větev ještě není na GitHubu (schránka ji posílá do minuty). */
    case Waiting = 'waiting';

    /** Pull request založený, běží testy. */
    case Testing = 'testing';

    /** Sloučeno do main, běží nasazení. */
    case Deploying = 'deploying';

    case Done = 'done';

    case TestsFailed = 'tests_failed';

    /** Main se mezitím změnil tak, že se úprava nedá sloučit sama. */
    case Conflict = 'conflict';

    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Waiting => 'Odesílá se na GitHub',
            self::Testing => 'Běží testy',
            self::Deploying => 'Nasazuje se na web',
            self::Done => 'Na webu',
            self::TestsFailed => 'Testy neprošly',
            self::Conflict => 'Nejde sloučit',
            self::Failed => 'Nepovedlo se',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Waiting, self::Testing, self::Deploying => 'warning',
            self::Done => 'success',
            self::TestsFailed, self::Conflict, self::Failed => 'danger',
        };
    }

    public function isRunning(): bool
    {
        return in_array($this, [self::Waiting, self::Testing, self::Deploying], true);
    }

    /** Stav kroku 1 (testy), 2 (nasazení) a 3 (na webu) v průběhu: done / current / todo. */
    public function stepState(int $step): string
    {
        $current = match ($this) {
            self::Deploying => 2,
            self::Done => 4,
            default => 1,
        };

        return match (true) {
            $step < $current => 'done',
            $step === $current => 'current',
            default => 'todo',
        };
    }
}
