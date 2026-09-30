<?php

namespace App\Support\Ads;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Jak se čerpá měsíční rozpočet. Počítá se k včerejšku, dnešní útrata
 * ještě není celá.
 */
final class BudgetPace
{
    private function __construct(
        public readonly float $budget,
        public readonly float $spent,
        public readonly float $expected,
        public readonly int $daysElapsed,
        public readonly int $daysInMonth,
    ) {}

    /**
     * @param  list<int>  $accountIds
     */
    public static function for(array $accountIds, ?float $budget, ?CarbonInterface $today = null): ?self
    {
        if (! $budget || $budget <= 0) {
            return null;
        }

        $today = CarbonImmutable::parse($today ?? now())->startOfDay();
        $yesterday = $today->subDay();

        // Prvního v měsíci se ještě nic nečerpalo.
        if ($today->day === 1) {
            return new self($budget, 0, 0, 0, $today->daysInMonth);
        }

        $spent = Stats::totals($accountIds, Period::between($today->startOfMonth(), $yesterday))->spend();
        $elapsed = $yesterday->day;

        return new self($budget, $spent, $budget * $elapsed / $today->daysInMonth, $elapsed, $today->daysInMonth);
    }

    /** Vyčerpáno z celého měsíčního rozpočtu, v procentech. */
    public function spentPercent(): float
    {
        return $this->spent / $this->budget * 100;
    }

    /** Čerpání proti poměrné části k dnešku, v procentech. 100 = přesně podle plánu. */
    public function pacePercent(): ?float
    {
        return $this->expected > 0 ? $this->spent / $this->expected * 100 : null;
    }

    /** Odhad útraty za celý měsíc při dosavadním tempu. */
    public function projected(): ?float
    {
        return $this->daysElapsed > 0 ? $this->spent / $this->daysElapsed * $this->daysInMonth : null;
    }
}
