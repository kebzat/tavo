<?php

namespace App\Support\Ads\Rules;

use App\Enums\Ads\PrimaryGoal;
use App\Models\Ads\AdAccount;
use App\Models\Ads\AdCampaignGroup;
use App\Models\Ads\AdClientSettings;
use App\Models\Client;
use App\Settings\AdsSettings;
use App\Support\Ads\AdsScope;
use App\Support\Ads\DataStatus;
use App\Support\Ads\Metrics;
use App\Support\Ads\Period;
use App\Support\Ads\Stats;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Všechno, co pravidla o klientovi nebo skupině kampaní potřebují. Součty
 * se počítají jednou.
 *
 * U skupiny se čísla, cíl, cílová cena, ROAS, rozpočet a prahy berou ze
 * skupiny (co nemá vyplněné, převezme od klienta).
 */
final class AlertContext
{
    public readonly AdsScope $scope;

    /** @var array<string, Metrics> */
    private array $totals = [];

    /** @var array<string, array<string, Metrics>> */
    private array $daily = [];

    private ?DataStatus $status = null;

    public function __construct(
        public readonly Client $client,
        public readonly AdsSettings $settings,
        public readonly CarbonImmutable $today,
        ?AdsScope $scope = null,
    ) {
        $this->scope = $scope ?? new AdsScope($client);
    }

    public function group(): ?AdCampaignGroup
    {
        return $this->scope->group;
    }

    /** @return Collection<int, AdAccount> */
    public function accounts(): Collection
    {
        return $this->client->adAccounts->where('is_active', true)->values();
    }

    /** @return list<int> */
    public function accountIds(): array
    {
        return $this->scope->accountIds();
    }

    public function clientSettings(): ?AdClientSettings
    {
        return $this->client->adSettings;
    }

    public function targetCpa(): ?float
    {
        return $this->scope->targetCpa();
    }

    public function targetRoas(): ?float
    {
        return $this->scope->targetRoas();
    }

    public function monthlyBudget(): ?float
    {
        return $this->scope->monthlyBudget();
    }

    public function dataStatus(): DataStatus
    {
        return $this->status ??= DataStatus::for($this->client->adAccounts, $this->today);
    }

    /**
     * Práh pravidla: hodnota skupiny, klienta (Cíle a paušál → Upozornění),
     * jinak globální z Nastavení reklam. Klíče jsou názvy vlastností AdsSettings.
     */
    public function threshold(string $key): int|float
    {
        $own = $this->overrides()[$key] ?? null;

        return is_numeric($own) ? $own + 0 : $this->settings->{$key};
    }

    /** @return array<string, mixed> */
    public function overrides(): array
    {
        return $this->scope->alertOverrides();
    }

    public function goal(): PrimaryGoal
    {
        return $this->scope->goal();
    }

    public function currency(): string
    {
        return $this->client->adCurrency();
    }

    public function yesterday(): CarbonImmutable
    {
        return $this->today->subDay();
    }

    /** Posledních 7 celých dní. */
    public function week(): Period
    {
        return Period::preset('7d', $this->today);
    }

    /** Posledních N celých dní. */
    public function lastDays(int $days): Period
    {
        return Period::between($this->yesterday()->subDays($days - 1), $this->yesterday());
    }

    public function totals(Period $period): Metrics
    {
        return $this->totals[$period->label()] ??= Stats::totals($this->accountIds(), $period, $this->scope->campaignIds());
    }

    /** @return array<string, Metrics> */
    public function daily(Period $period): array
    {
        return $this->daily[$period->label()] ??= Stats::daily($this->accountIds(), $period, $this->scope->campaignIds());
    }

    /**
     * Plní skupina (nebo klient) obchodní cíl? Pokles CTR pak sám o sobě
     * není problém. Null, když cíl není nebo je málo konverzí.
     */
    public function meetsTarget(Metrics $week): ?bool
    {
        $goal = $this->goal();

        if (! $goal->isPerformance() || $week->conversions($goal) < $this->threshold('min_conversions')) {
            return null;
        }

        $cpa = $this->targetCpa();
        $roas = $goal->hasValue() ? $this->targetRoas() : null;

        if (! $cpa && ! $roas) {
            return null;
        }

        $cost = $week->costPerConversion($goal);
        $okCpa = ! $cpa || ($cost !== null && $cost <= $cpa);
        $okRoas = ! $roas || ($week->roas() !== null && $week->roas() >= $roas);

        return $okCpa && $okRoas;
    }

    /** „Skupina Prodej: “ před titulek upozornění. U celého účtu nic. */
    public function prefix(): string
    {
        return $this->scope->isGroup() ? $this->scope->label().': ' : '';
    }
}
