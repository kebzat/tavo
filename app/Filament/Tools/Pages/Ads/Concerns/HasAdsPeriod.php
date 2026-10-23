<?php

namespace App\Filament\Tools\Pages\Ads\Concerns;

use App\Support\Ads\Period;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;

/**
 * Volba období na stránkách reklam: přednastavená období, vlastní od–do
 * a s čím je porovnat. Všechno se počítá z naší databáze, na reklamní
 * systémy se nesahá.
 *
 * Stránka sama definuje `$period` s výchozí hodnotou.
 */
trait HasAdsPeriod
{
    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $to = null;

    /** S čím porovnat, klíč z Period::COMPARISONS. */
    #[Url(as: 'srovnani')]
    public string $compare = 'previous';

    public function currentPeriod(): Period
    {
        if ($this->period === 'custom' && $this->from && $this->to) {
            return Period::custom($this->from, $this->to);
        }

        return Period::preset($this->period);
    }

    /** Srovnávací období podle zvolené volby. */
    public function comparisonPeriod(): Period
    {
        return $this->currentPeriod()->comparison($this->compare);
    }

    /** @return array<string, string> */
    public function periods(): array
    {
        return Period::PRESETS;
    }

    /** @return array<string, string> */
    public function comparisons(): array
    {
        return Period::COMPARISONS;
    }

    public function setPeriod(string $period): void
    {
        $this->period = array_key_exists($period, Period::PRESETS) ? $period : '7d';
        $this->from = null;
        $this->to = null;
    }

    public function setCompare(string $compare): void
    {
        $this->compare = array_key_exists($compare, Period::COMPARISONS) ? $compare : 'previous';
    }

    /** „16.–22. 10. 2026, srovnání s 9.–15. 10. 2026“ */
    public function periodHeading(): string
    {
        return $this->currentPeriod()->label().', srovnání s '.$this->comparisonPeriod()->label();
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
        $query = $this->period === 'custom'
            ? ['period' => 'custom', 'from' => $this->from, 'to' => $this->to]
            : ['period' => $this->period];

        return $this->compare === 'previous' ? $query : $query + ['srovnani' => $this->compare];
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
