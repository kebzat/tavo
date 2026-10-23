<?php

namespace App\Support\Ads;

use App\Enums\Ads\PrimaryGoal;
use App\Models\Ads\AdCampaignGroup;
use App\Models\Client;

/**
 * Na co se díváme: celý účet klienta, nebo jen skupina kampaní („Prodej“,
 * „Nábor“). Skupina má vlastní cíl, rozpočet, cílovou cenu, ROAS, čísla
 * a prahy upozornění; co nemá vyplněné, převezme od klienta.
 *
 * Celý účet sčítá všechny kampaně, i nezařazené a mimo výkonnostní, ať
 * se neztratí žádná útrata.
 */
final class AdsScope
{
    /** Klíč adresy pro kampaně mimo skupiny. */
    public const OUTSIDE = 'mimo';

    /** @var list<int>|null */
    private ?array $campaignIds = null;

    /**
     * @param  bool  $outside  kampaně mimo všechny skupiny (boosting, testy, co nikam nepatří)
     */
    public function __construct(
        public readonly Client $client,
        public readonly ?AdCampaignGroup $group = null,
        public readonly bool $outside = false,
    ) {}

    /** Skupina podle klíče z adresy (ID, „mimo“), cizí nebo neexistující = celý účet. */
    public static function for(Client $client, int|string|null $key = null): self
    {
        if ($key === self::OUTSIDE && $client->adCampaignGroups()->exists()) {
            return new self($client, outside: true);
        }

        $group = is_numeric($key)
            ? $client->adCampaignGroups()->whereKey((int) $key)->first()
            : null;

        return new self($client, $group);
    }

    /** Jen část účtu (skupina nebo kampaně mimo skupiny)? */
    public function isGroup(): bool
    {
        return $this->group !== null || $this->outside;
    }

    /** Klíč do adresy a do otisku upozornění: ID skupiny, „mimo“, nebo „all“. */
    public function key(): string
    {
        return match (true) {
            $this->group !== null => (string) $this->group->getKey(),
            $this->outside => self::OUTSIDE,
            default => 'all',
        };
    }

    public function label(): string
    {
        return match (true) {
            $this->group !== null => $this->group->name,
            $this->outside => 'Mimo skupiny',
            default => 'Celý účet',
        };
    }

    /** @return list<int> */
    public function accountIds(): array
    {
        return $this->client->activeAdAccountIds();
    }

    /** @return list<int>|null null = všechny kampaně účtu */
    public function campaignIds(): ?array
    {
        return match (true) {
            $this->group !== null => $this->campaignIds ??= $this->group->campaignIds(),
            $this->outside => $this->campaignIds ??= CampaignGroups::campaigns($this->client)->whereNull('ad_campaign_group_id')->pluck('id')->all(),
            default => null,
        };
    }

    public function goal(): PrimaryGoal
    {
        return $this->group?->goal ?? $this->client->adGoal();
    }

    public function currency(): string
    {
        return $this->client->adCurrency();
    }

    /**
     * Rozpočet: skupina má vlastní. Celý účet bere rozpočet klienta.
     */
    public function monthlyBudget(): ?float
    {
        return $this->isGroup() ? $this->group?->monthly_budget : $this->client->adSettings?->monthly_budget;
    }

    public function targetCpa(): ?float
    {
        return $this->isGroup() ? $this->group?->target_cpa : $this->client->adSettings?->target_cpa;
    }

    public function targetRoas(): ?float
    {
        return $this->isGroup() ? $this->group?->target_roas : $this->client->adSettings?->target_roas;
    }

    /**
     * Výběr čísel a sekcí. Skupina bez vlastního výběru jede podle šablony
     * svého cíle, sekce převezme od klienta.
     *
     * @return array{tiles?: ?list<string>, sections?: ?list<string>}|null
     */
    public function dashboard(): ?array
    {
        $client = $this->client->adSettings?->dashboard;

        if (! $this->isGroup()) {
            return $client;
        }

        return [
            'tiles' => $this->group?->dashboard['tiles'] ?? null,
            'sections' => $this->group->dashboard['sections'] ?? $client['sections'] ?? null,
        ];
    }

    /**
     * Prahy upozornění: skupina přebíjí klienta, klient globální nastavení.
     *
     * @return array<string, mixed>
     */
    public function alertOverrides(): array
    {
        return array_merge($this->client->adSettings?->alert_overrides ?? [], $this->group?->alert_overrides ?? []);
    }

    /** @return array{reach: float, impressions: float, unique_link_clicks: float}|null */
    public function periodReach(Period $period): ?array
    {
        return match (true) {
            $this->group !== null => GroupReach::total($this->group, $period),
            // Mimo skupiny dosah nestahujeme, lidi by nešlo poctivě oddělit.
            $this->outside => null,
            default => PeriodReach::total(PeriodReach::accountIds($this->client->adAccounts), $period),
        };
    }

    public function totals(Period $period): Metrics
    {
        return Stats::totals($this->accountIds(), $period, $this->campaignIds());
    }

    public function budgetPace(?DataStatus $status = null): ?BudgetPace
    {
        return BudgetPace::for($this->accountIds(), $this->monthlyBudget(), null, $status?->completeThrough, $this->campaignIds());
    }
}
