<?php

namespace App\Support\Ads;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Jak se čerpá měsíční rozpočet. Počítá se k včerejšku, dnešní útrata
 * ještě není celá.
 *
 * Když chybí stažená data (výpadek stahování), počítá se jen k poslednímu
 * kompletnímu dni. Jinak by chybějící dny vypadaly jako nulová útrata
 * a vyšlo by „čerpá se pomalu“, i když kampaně jedou naplno.
 */
final class BudgetPace
{
    private function __construct(
        public readonly float $budget,
        public readonly float $spent,
        public readonly float $expected,
        public readonly int $daysElapsed,
        public readonly int $daysInMonth,
        public readonly ?CarbonImmutable $through = null,
        public readonly bool $partial = false,
    ) {}

    /**
     * @param  list<int>  $accountIds
     * @param  ?CarbonInterface  $completeThrough  poslední den s kompletními daty (DataStatus), null = nevíme, bereme včerejšek
     * @param  list<int>|null  $campaignIds  jen kampaně skupiny
     */
    public static function for(array $accountIds, ?float $budget, ?CarbonInterface $today = null, ?CarbonInterface $completeThrough = null, ?array $campaignIds = null): ?self
    {
        if (! $budget || $budget <= 0) {
            return null;
        }

        $today = CarbonImmutable::parse($today ?? now())->startOfDay();
        $through = $today->subDay();
        $partial = false;

        if ($completeThrough !== null && CarbonImmutable::parse($completeThrough)->lt($through)) {
            $through = CarbonImmutable::parse($completeThrough)->startOfDay();
            $partial = true;
        }

        // Prvního v měsíci se ještě nic nečerpalo. Stejně tak, když kompletní
        // data končí v minulém měsíci: o tomhle měsíci nevíme nic.
        if ($today->day === 1 || $through->lt($today->startOfMonth())) {
            return new self($budget, 0, 0, 0, $today->daysInMonth, null, $partial);
        }

        $spent = Stats::totals($accountIds, Period::between($today->startOfMonth(), $through), $campaignIds)->spend();
        $elapsed = $through->day;

        return new self($budget, $spent, $budget * $elapsed / $today->daysInMonth, $elapsed, $today->daysInMonth, $through, $partial);
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

    /** Poznámka k číslům, když nejsou spočítaná k včerejšku. */
    public function note(): ?string
    {
        return match (true) {
            $this->partial && $this->through !== null => 'spočítáno k '.$this->through->format('j. n.').', novější data chybí',
            $this->partial => 'data za tento měsíc chybí',
            default => null,
        };
    }
}
