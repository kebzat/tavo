<?php

namespace App\Support\Ads;

use App\Enums\Ads\ReportStatus;
use App\Enums\Ads\ReportType;
use App\Models\Ads\AdCampaignGroup;
use App\Models\Ads\AdReport;
use App\Models\Client;

/**
 * Zakládá koncepty reportů se zmrazenými čísly. Report se nikdy neodešle
 * sám: komentář k číslům píše Pavel a teprve pak ho klient dostane.
 */
class ReportBuilder
{
    public function create(Client $client, ReportType $type, Period $period, ?AdsScope $scope = null): AdReport
    {
        $scope ??= new AdsScope($client);

        return $client->adReports()->create([
            'ad_campaign_group_id' => $scope->group?->getKey(),
            'type' => $type,
            'period_start' => $period->from,
            'period_end' => $period->to,
            'title' => $type->title().' '.$period->label().($scope->group ? ' · '.$scope->group->name : ''),
            'snapshot' => ClientPerformance::build($client, $period, $scope),
            'sources' => self::sources($client),
            'status' => ReportStatus::Draft,
        ]);
    }

    /**
     * Přepočítá čísla konceptu z aktuálních dat. Komentář zůstane. Schválený
     * a odeslaný report má čísla zamčená, ten se nepřepočítává.
     */
    public function refresh(AdReport $report): AdReport
    {
        if ($report->status !== ReportStatus::Draft) {
            return $report;
        }

        $report->update([
            'snapshot' => ClientPerformance::build($report->client, Period::between($report->period_start, $report->period_end), $this->scope($report)),
            'sources' => self::sources($report->client),
        ]);

        return $report;
    }

    /** Schválí koncept: čísla se zamknou tak, jak jsou. */
    public function approve(AdReport $report): AdReport
    {
        $report->update(['status' => ReportStatus::Approved, 'approved_at' => now(), 'approved_by' => auth()->id()]);

        return $report;
    }

    /** Vrátí schválený (neodeslaný) report do konceptu, čísla jde zase obnovit. */
    public function reopen(AdReport $report): AdReport
    {
        if ($report->status === ReportStatus::Approved) {
            $report->update(['status' => ReportStatus::Draft, 'approved_at' => null, 'approved_by' => null]);
        }

        return $report;
    }

    /**
     * Jak se od zmrazení změnila hlavní čísla v datech (Meta dopočítává
     * konverze). Report se nemění, jen to ukážeme, ať o tom víme.
     *
     * @return list<array{label: string, frozen: string, now: string}>
     */
    public function drift(AdReport $report): array
    {
        $frozen = new PerformanceView($report->snapshot);
        $current = Stats::totals($report->client->activeAdAccountIds(), Period::between($report->period_start, $report->period_end), $this->scope($report)->campaignIds());
        $goal = $frozen->goal;
        $rows = [];

        foreach (['spend', 'conversions', 'purchase_value'] as $key) {
            if (! MetricCatalog::appliesTo($key, $goal)) {
                continue;
            }

            $then = MetricCatalog::value($key, $frozen->totals, $goal);
            $now = MetricCatalog::value($key, $current, $goal);

            if ($then !== null && $now !== null && abs($now - $then) >= max(0.5, abs($then) * 0.01)) {
                $rows[] = [
                    'label' => MetricCatalog::label($key, $goal),
                    'frozen' => MetricCatalog::format($key, $then, $frozen->currency),
                    'now' => MetricCatalog::format($key, $now, $frozen->currency),
                ];
            }
        }

        return $rows;
    }

    private function scope(AdReport $report): AdsScope
    {
        return new AdsScope($report->client, $report->group);
    }

    /**
     * Odkud čísla jsou a jak čerstvá: platforma, účet a kdy se naposledy
     * stáhl. Klient i Pavel vidí, že chybějící data nejsou nula.
     *
     * @return list<array{platform: string, account: string, synced_at: ?string, error: ?string}>
     */
    public static function sources(Client $client): array
    {
        return $client->adAccounts()->where('is_active', true)->get()->map(fn ($account): array => [
            'platform' => $account->platform->getLabel(),
            'account' => $account->name,
            'synced_at' => $account->last_synced_at?->toIso8601String(),
            'error' => $account->last_sync_error,
        ])->values()->all();
    }

    /** Existuje už report téhož typu za stejné období? Pondělní běh ho pak nezakládá znovu. */
    public function exists(Client $client, ReportType $type, Period $period, ?AdCampaignGroup $group = null): bool
    {
        return $client->adReports()
            ->where('ad_campaign_group_id', $group?->getKey())
            ->where('type', $type)
            ->whereDate('period_start', $period->from)
            ->whereDate('period_end', $period->to)
            ->exists();
    }

    /**
     * Komu report poslat: adresy z nastavení klienta, jinak kontakt klienta.
     *
     * @return list<string>
     */
    public function recipients(Client $client): array
    {
        $configured = collect($client->adSettings?->report_recipients ?? [])
            ->map(fn ($email): string => trim((string) $email))
            ->filter()
            ->values()
            ->all();

        return $configured ?: array_values(array_filter([$client->contact_email]));
    }
}
