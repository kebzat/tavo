<?php

namespace App\Support\Ads;

use App\Models\Ads\AnalyticsDailyStat;
use Illuminate\Database\Eloquent\Builder;

/** Součty z analytics_daily_stats (GA4). */
final class AnalyticsStats
{
    /** Kanály GA4 česky. Neznámý kanál zůstane anglicky. */
    public const CHANNELS = [
        'Organic Search' => 'Vyhledávání (neplacené)',
        'Paid Search' => 'Vyhledávání (placené)',
        'Organic Social' => 'Sociální sítě (neplacené)',
        'Paid Social' => 'Sociální sítě (placené)',
        'Direct' => 'Přímý přístup',
        'Referral' => 'Odkazy z jiných webů',
        'Email' => 'E-mail',
        'Organic Shopping' => 'Srovnávače (neplacené)',
        'Paid Shopping' => 'Srovnávače (placené)',
        'Display' => 'Obsahová síť',
        'Paid Video' => 'Video (placené)',
        'Organic Video' => 'Video (neplacené)',
        'Cross-network' => 'Více sítí (Performance Max)',
        'Unassigned' => 'Nezařazeno',
    ];

    /**
     * @param  list<int>  $accountIds
     * @return array<string, float>
     */
    public static function totals(array $accountIds, Period $period): array
    {
        $row = self::query($accountIds, $period)->selectRaw(self::sums())->first();

        return self::normalize($row?->getAttributes() ?? []);
    }

    /**
     * @param  list<int>  $accountIds
     * @return list<array<string, mixed>> kanál + součty, od největší návštěvnosti
     */
    public static function channels(array $accountIds, Period $period): array
    {
        return self::query($accountIds, $period)
            ->selectRaw('channel, '.self::sums())
            ->groupBy('channel')
            ->orderByDesc('sessions')
            ->get()
            ->map(fn (AnalyticsDailyStat $row): array => ['channel' => (string) $row->channel] + self::normalize($row->getAttributes()))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, float>
     */
    public static function normalize(array $row): array
    {
        $sums = [];

        foreach (AnalyticsDailyStat::SUMS as $column) {
            $sums[$column] = (float) ($row[$column] ?? 0);
        }

        return $sums;
    }

    /** @param  list<int>  $accountIds */
    private static function query(array $accountIds, Period $period): Builder
    {
        return AnalyticsDailyStat::query()
            ->whereIn('ad_account_id', $accountIds)
            ->whereBetween('date', $period->bounds());
    }

    private static function sums(): string
    {
        return collect(AnalyticsDailyStat::SUMS)
            ->map(fn (string $column): string => "coalesce(sum({$column}), 0) as {$column}")
            ->implode(', ');
    }
}
