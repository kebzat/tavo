<?php

namespace App\Support\Ads;

use App\Enums\Ads\PrimaryGoal;
use App\Models\Ads\AdDailyStat;

/**
 * Součty za období a poměry z nich spočítané.
 *
 * Poměry se nikdy nepočítají jako průměr denních poměrů: CTR za týden je
 * součet prokliků děleno součtem zobrazení, ne průměr sedmi denních CTR.
 * Když jmenovatel chybí, poměr je null a dlaždice ukáže pomlčku.
 */
final class Metrics
{
    /** @param  array<string, float>  $sums */
    private function __construct(private readonly array $sums) {}

    /** @param  array<string, mixed>  $sums */
    public static function fromArray(array $sums): self
    {
        $normalized = [];

        foreach (AdDailyStat::SUMS as $column) {
            $normalized[$column] = (float) ($sums[$column] ?? 0);
        }

        return new self($normalized);
    }

    public static function empty(): self
    {
        return self::fromArray([]);
    }

    /** @return array<string, float> */
    public function toArray(): array
    {
        return $this->sums;
    }

    public function get(string $column): float
    {
        return $this->sums[$column] ?? 0.0;
    }

    public function spend(): float
    {
        return $this->sums['spend'];
    }

    public function hasData(): bool
    {
        return $this->sums['spend'] > 0 || $this->sums['impressions'] > 0;
    }

    public function conversions(PrimaryGoal $goal): float
    {
        return match ($goal) {
            PrimaryGoal::Purchases => $this->sums['purchases'],
            PrimaryGoal::Leads => $this->sums['leads'],
            PrimaryGoal::Traffic => $this->sums['link_clicks'],
        };
    }

    /** Cena za konverzi (CPA, CPL, u návštěvnosti CPC). */
    public function costPerConversion(PrimaryGoal $goal): ?float
    {
        return self::ratio($this->sums['spend'], $this->conversions($goal));
    }

    /** Hodnota nákupů děleno útratou. */
    public function roas(): ?float
    {
        return self::ratio($this->sums['purchase_value'], $this->sums['spend']);
    }

    /** Míra prokliku na web v procentech. */
    public function ctr(): ?float
    {
        $ratio = self::ratio($this->sums['link_clicks'], $this->sums['impressions']);

        return $ratio === null ? null : $ratio * 100;
    }

    public function cpc(): ?float
    {
        return self::ratio($this->sums['spend'], $this->sums['link_clicks']);
    }

    public function cpm(): ?float
    {
        $ratio = self::ratio($this->sums['spend'], $this->sums['impressions']);

        return $ratio === null ? null : $ratio * 1000;
    }

    /**
     * Frekvence ze součtu denních dosahů. Denní dosahy se překrývají, takže
     * vychází nižší než skutečná frekvence za období. Pro upozornění je to
     * opatrný odhad: když svítí tohle, skutečnost je horší.
     */
    public function frequency(): ?float
    {
        return self::ratio($this->sums['impressions'], $this->sums['reach']);
    }

    /** Podíl prokliků, které skončily konverzí, v procentech. */
    public function conversionRate(PrimaryGoal $goal): ?float
    {
        if ($goal === PrimaryGoal::Traffic) {
            return null;
        }

        $ratio = self::ratio($this->conversions($goal), $this->sums['link_clicks']);

        return $ratio === null ? null : $ratio * 100;
    }

    public function averageOrderValue(): ?float
    {
        return self::ratio($this->sums['purchase_value'], $this->sums['purchases']);
    }

    public function plus(self $other): self
    {
        $sums = [];

        foreach ($this->sums as $column => $value) {
            $sums[$column] = $value + $other->sums[$column];
        }

        return new self($sums);
    }

    /** Relativní změna v procentech. Null, když srovnávat není s čím. */
    public static function change(?float $current, ?float $previous): ?float
    {
        if ($current === null || $previous === null || $previous == 0.0) {
            return null;
        }

        return ($current - $previous) / abs($previous) * 100;
    }

    private static function ratio(float $numerator, float $denominator): ?float
    {
        return $denominator > 0 ? $numerator / $denominator : null;
    }
}
