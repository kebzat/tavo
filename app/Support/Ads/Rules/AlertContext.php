<?php

namespace App\Support\Ads\Rules;

use App\Enums\Ads\PrimaryGoal;
use App\Models\Ads\AdAccount;
use App\Models\Ads\AdClientSettings;
use App\Models\Client;
use App\Settings\AdsSettings;
use App\Support\Ads\Metrics;
use App\Support\Ads\Period;
use App\Support\Ads\Stats;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Všechno, co pravidla o klientovi potřebují. Součty se počítají jednou. */
final class AlertContext
{
    /** @var array<string, Metrics> */
    private array $totals = [];

    /** @var array<string, array<string, Metrics>> */
    private array $daily = [];

    public function __construct(
        public readonly Client $client,
        public readonly AdsSettings $settings,
        public readonly CarbonImmutable $today,
    ) {}

    /** @return Collection<int, AdAccount> */
    public function accounts(): Collection
    {
        return $this->client->adAccounts->where('is_active', true)->values();
    }

    /** @return list<int> */
    public function accountIds(): array
    {
        return $this->client->activeAdAccountIds();
    }

    public function clientSettings(): ?AdClientSettings
    {
        return $this->client->adSettings;
    }

    public function goal(): PrimaryGoal
    {
        return $this->client->adGoal();
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
        return $this->totals[$period->label()] ??= Stats::totals($this->accountIds(), $period);
    }

    /** @return array<string, Metrics> */
    public function daily(Period $period): array
    {
        return $this->daily[$period->label()] ??= Stats::daily($this->accountIds(), $period);
    }
}
