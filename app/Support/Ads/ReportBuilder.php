<?php

namespace App\Support\Ads;

use App\Enums\Ads\ReportStatus;
use App\Enums\Ads\ReportType;
use App\Models\Ads\AdReport;
use App\Models\Client;

/**
 * Zakládá koncepty reportů se zmrazenými čísly. Report se nikdy neodešle
 * sám: komentář k číslům píše Pavel a teprve pak ho klient dostane.
 */
class ReportBuilder
{
    public function create(Client $client, ReportType $type, Period $period): AdReport
    {
        return $client->adReports()->create([
            'type' => $type,
            'period_start' => $period->from,
            'period_end' => $period->to,
            'title' => $type->title().' '.$period->label(),
            'snapshot' => ClientPerformance::build($client, $period),
            'sources' => self::sources($client),
            'status' => ReportStatus::Draft,
        ]);
    }

    /** Přepočítá čísla konceptu z aktuálních dat. Komentář zůstane. */
    public function refresh(AdReport $report): AdReport
    {
        $report->update([
            'snapshot' => ClientPerformance::build($report->client, Period::between($report->period_start, $report->period_end)),
            'sources' => self::sources($report->client),
        ]);

        return $report;
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
    public function exists(Client $client, ReportType $type, Period $period): bool
    {
        return $client->adReports()
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
