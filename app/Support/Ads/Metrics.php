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
    /**
     * Přesný dosah za celé období (PeriodReach) je zvlášť, null = nemáme.
     *
     * @param  array<string, float>  $sums
     * @param  array{reach: float, impressions: float, unique_link_clicks: float}|null  $periodReach
     */
    private function __construct(
        private readonly array $sums,
        private readonly ?array $periodReach = null,
    ) {}

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

    /**
     * Stejné součty s přesným dosahem za celé období. Bez něj jsou dosah,
     * frekvence a unikátní CTR null.
     *
     * @param  array<string, mixed>|null  $periodReach
     */
    public function withPeriodReach(?array $periodReach): self
    {
        if ($periodReach === null) {
            return new self($this->sums);
        }

        return new self($this->sums, [
            'reach' => (float) ($periodReach['reach'] ?? 0),
            'impressions' => (float) ($periodReach['impressions'] ?? 0),
            'unique_link_clicks' => (float) ($periodReach['unique_link_clicks'] ?? 0),
        ]);
    }

    public function hasPeriodReach(): bool
    {
        return $this->periodReach !== null;
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

    /** CTR ze všech kliknutí (i na profil, „více“, obrázek), v procentech. */
    public function ctrAll(): ?float
    {
        return self::percent($this->sums['clicks'], $this->sums['impressions']);
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

    /** Lidé zasažení za celé období, bez opakování mezi dny. */
    public function reach(): ?float
    {
        return $this->periodReach['reach'] ?? null;
    }

    /** Přesná frekvence za období: zobrazení na člověka podle dosahu za celé období. */
    public function periodFrequency(): ?float
    {
        return $this->periodReach === null ? null : self::ratio($this->periodReach['impressions'], $this->periodReach['reach']);
    }

    /** Unikátní CTR odkazu: kolik zasažených lidí kliklo na web, v procentech. */
    public function uniqueCtr(): ?float
    {
        return $this->periodReach === null ? null : self::percent($this->periodReach['unique_link_clicks'], $this->periodReach['reach']);
    }

    public function costPerAddToCart(): ?float
    {
        return self::ratio($this->sums['spend'], $this->sums['add_to_cart']);
    }

    public function costPerCheckout(): ?float
    {
        return self::ratio($this->sums['spend'], $this->sums['checkouts']);
    }

    public function costPerLandingPageView(): ?float
    {
        return self::ratio($this->sums['spend'], $this->sums['landing_page_views']);
    }

    /** Kolik prokliků na web skončilo načtenou stránkou, v procentech. */
    public function landingPageViewRate(): ?float
    {
        return self::percent($this->sums['landing_page_views'], $this->sums['link_clicks']);
    }

    /** Konverze (nákupy, poptávky) na zobrazení cílové stránky, v procentech. */
    public function landingPageConversionRate(PrimaryGoal $goal): ?float
    {
        return $goal === PrimaryGoal::Traffic ? null : self::percent($this->conversions($goal), $this->sums['landing_page_views']);
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

    /** Součet dvou období nebo účtů. Přesný dosah se sečíst nedá, výsledek ho nemá. */
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

    private static function percent(float $numerator, float $denominator): ?float
    {
        $ratio = self::ratio($numerator, $denominator);

        return $ratio === null ? null : $ratio * 100;
    }
}
