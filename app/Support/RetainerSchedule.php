<?php

namespace App\Support;

use App\Models\Client;
use App\Models\ClientRetainer;
use App\Support\Ads\Format;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Paušál po obdobích od tohoto měsíce dál: „říjen – listopad 2026 30 000 Kč,
 * od února 2027 5 000 Kč“. Sousední měsíce se stejnými částkami se slijí
 * do jednoho období. Pro sekci Cena spolupráce na přehledu klienta
 * a její náhled na kartě klienta.
 */
final class RetainerSchedule
{
    /** Jak daleko dopředu hledat změny. Co platí i za tím, je „od …“ bez konce. */
    private const HORIZON_MONTHS = 36;

    /**
     * @return list<array{label: string, total: string, parts: list<array{label: string, fee: string}>, tentative: bool}>
     */
    public static function for(Client $client, ?CarbonImmutable $from = null): array
    {
        $from = ($from ?? CarbonImmutable::now())->startOfMonth();
        $retainers = $client->retainers;

        /** @var list<array{month: CarbonImmutable, active: Collection<int, ClientRetainer>, key: string}> $months */
        $months = [];

        for ($offset = 0; $offset < self::HORIZON_MONTHS; $offset++) {
            $month = $from->addMonthsNoOverflow($offset);
            $active = $retainers->filter(fn (ClientRetainer $retainer): bool => $retainer->activeIn($month))->values();
            $months[] = [
                'month' => $month,
                'active' => $active,
                'key' => $active->map(fn (ClientRetainer $retainer): string => $retainer->label.':'.$retainer->monthly_fee.':'.(int) $retainer->is_tentative)->sort()->implode('|'),
            ];
        }

        $periods = [];

        foreach ($months as $index => $month) {
            if ($index > 0 && $month['key'] === $months[$index - 1]['key']) {
                $periods[count($periods) - 1]['to'] = $month['month'];

                continue;
            }

            $periods[] = ['from' => $month['month'], 'to' => $month['month'], 'active' => $month['active']];
        }

        $last = end($months)['month'];

        return collect($periods)
            ->filter(fn (array $period): bool => $period['active']->sum('monthly_fee') > 0)
            ->map(fn (array $period): array => [
                'label' => self::label($period['from'], $period['to']->isSameMonth($last) ? null : $period['to']),
                'total' => Format::money($period['active']->sum('monthly_fee')),
                'parts' => $period['active']->count() > 1
                    ? $period['active']->map(fn (ClientRetainer $retainer): array => ['label' => $retainer->label, 'fee' => Format::money($retainer->monthly_fee)])->values()->all()
                    : [],
                'tentative' => $period['active']->contains('is_tentative', true),
            ])
            ->values()
            ->all();
    }

    /** „prosinec 2026 – leden 2027“, „říjen – listopad 2026“, „březen 2027“, „od února 2027“ */
    private static function label(CarbonImmutable $from, ?CarbonImmutable $to): string
    {
        if ($to === null) {
            return 'od '.self::genitive($from);
        }

        if ($from->isSameMonth($to)) {
            return $from->translatedFormat('F Y');
        }

        return $from->year === $to->year
            ? $from->translatedFormat('F').' – '.$to->translatedFormat('F Y')
            : $from->translatedFormat('F Y').' – '.$to->translatedFormat('F Y');
    }

    private static function genitive(CarbonImmutable $month): string
    {
        $names = [1 => 'ledna', 'února', 'března', 'dubna', 'května', 'června', 'července', 'srpna', 'září', 'října', 'listopadu', 'prosince'];

        return $names[$month->month].' '.$month->year;
    }
}
