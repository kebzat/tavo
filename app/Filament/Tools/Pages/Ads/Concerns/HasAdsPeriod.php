<?php

namespace App\Filament\Tools\Pages\Ads\Concerns;

use App\Support\Ads\Period;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;

/**
 * Volba období na stránkách reklam: přednastavená období a vlastní od–do.
 * Všechno se počítá z naší databáze, na reklamní systémy se nesahá.
 *
 * Stránka sama definuje `$period` s výchozí hodnotou.
 */
trait HasAdsPeriod
{
    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $to = null;

    public function currentPeriod(): Period
    {
        if ($this->period === 'custom' && $this->from && $this->to) {
            return Period::custom($this->from, $this->to);
        }

        return Period::preset($this->period);
    }

    /** @return array<string, string> */
    public function periods(): array
    {
        return Period::PRESETS;
    }

    public function setPeriod(string $period): void
    {
        $this->period = array_key_exists($period, Period::PRESETS) ? $period : array_key_first(Period::PRESETS);
        $this->from = null;
        $this->to = null;
    }

    public function applyRange(): void
    {
        if (! $this->from || ! $this->to) {
            Notification::make()->warning()->title('Vyberte začátek i konec období')->send();

            return;
        }

        $period = Period::custom($this->from, $this->to);
        $this->period = 'custom';
        $this->from = $period->from->toDateString();
        $this->to = $period->to->toDateString();
    }

    /** Nejnovější den, který jde vybrat. Dnešek ještě není celý. */
    public function maxDate(): string
    {
        return Carbon::yesterday()->toDateString();
    }

    public function minDate(): string
    {
        return Carbon::today()->subMonthsNoOverflow(Period::MAX_HISTORY_MONTHS)->toDateString();
    }

    /** Parametry adresy, ať se zvolené období přenese na další stránku. */
    public function periodQuery(): array
    {
        return $this->period === 'custom'
            ? ['period' => 'custom', 'from' => $this->from, 'to' => $this->to]
            : ['period' => $this->period];
    }

    /**
     * Upozornění, když vybrané období začíná dřív, než máme čísla.
     *
     * @param  ?string  $since  Nejstarší den v databázi
     */
    protected function historyNote(?string $since, string $hint): ?string
    {
        if ($since === null || $this->currentPeriod()->from->gte(Carbon::parse($since))) {
            return null;
        }

        return 'Čísla máme od '.Carbon::parse($since)->format('j. n. Y').'. '.$hint;
    }
}
