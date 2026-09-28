<?php

namespace App\Support\Crm;

use App\Enums\Crm\DealStage;
use App\Models\Crm\Deal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Peníze v obchodech: co je jisté (vyhráno), co nejspíš přijde (částka
 * krát pravděpodobnost fáze) a kolik je ve hře celkem, kdyby vyšlo všechno.
 *
 * Pravděpodobnost bere každý obchod ze svého pole `probability`, které se
 * předvyplní podle fáze (DealStage::defaultProbability) a jde přepsat.
 */
class MoneyForecast
{
    /** @var Collection<int, Deal>|null */
    private ?Collection $open = null;

    /**
     * Tři hlavní čísla.
     *
     * @return array{won_month: int, won_year: int, weighted: int, potential: int, open_count: int}
     */
    public function summary(): array
    {
        return [
            'won_month' => $this->wonBetween(now()->startOfMonth(), now()->endOfMonth()),
            'won_year' => $this->wonBetween(now()->startOfYear(), now()->endOfYear()),
            'weighted' => (int) round($this->open()->sum(fn (Deal $deal): float => $deal->weightedValue())),
            'potential' => (int) $this->open()->sum('value_czk'),
            'open_count' => $this->open()->count(),
        ];
    }

    /**
     * Rozjednané obchody podle fáze, od nejvzdálenější k podpisu.
     *
     * @return Collection<int, array{label: string, count: int, total: int, weighted: int, probability: int}>
     */
    public function byStage(): Collection
    {
        $deals = $this->open()->groupBy(fn (Deal $deal): string => $deal->stage->value);

        return collect(DealStage::boardColumns())
            ->reject(fn (DealStage $stage): bool => $stage->isClosed())
            ->map(function (DealStage $stage) use ($deals): array {
                $inStage = $deals->get($stage->value, collect());

                return [
                    'label' => $stage->getLabel(),
                    'count' => $inStage->count(),
                    'total' => (int) $inStage->sum('value_czk'),
                    'weighted' => (int) round($inStage->sum(fn (Deal $deal): float => $deal->weightedValue())),
                    'probability' => $stage->defaultProbability(),
                ];
            })
            ->values();
    }

    /**
     * Měsíce od pěti zpět po dva dopředu. Minulé ukazují vyhrané peníze,
     * aktuální a budoucí navíc očekávané podle data uzavření obchodu.
     * Rozjednané obchody bez data uzavření se počítají do aktuálního měsíce.
     *
     * @return Collection<int, array{label: string, won: int, weighted: int, potential: int, current: bool, future: bool}>
     */
    public function months(int $back = 5, int $ahead = 2): Collection
    {
        $thisMonth = now()->startOfMonth();

        return collect(range(-$back, $ahead))->map(function (int $offset) use ($thisMonth): array {
            $start = $thisMonth->copy()->addMonths($offset);
            $end = $start->copy()->endOfMonth();

            $open = $offset < 0 ? collect() : $this->open()->filter(function (Deal $deal) use ($start, $end, $offset): bool {
                $close = $deal->expected_close_at;

                return $close === null || $close->lt(now()->startOfMonth())
                    ? $offset === 0
                    : $close->between($start, $end);
            });

            return [
                'label' => $this->monthLabel($start),
                'won' => $this->wonBetween($start, $end),
                'weighted' => (int) round($open->sum(fn (Deal $deal): float => $deal->weightedValue())),
                'potential' => (int) $open->sum('value_czk'),
                'current' => $offset === 0,
                'future' => $offset > 0,
            ];
        });
    }

    /** Krátký zápis částky do grafu: 85 tis., 1,2 mil. */
    public static function short(int $czk): string
    {
        return match (true) {
            $czk >= 1_000_000 => number_format($czk / 1_000_000, 1, ',', ' ').' mil.',
            $czk >= 1_000 => number_format($czk / 1_000, 0, ',', ' ').' tis.',
            default => number_format($czk, 0, ',', ' '),
        };
    }

    public static function czk(int $czk): string
    {
        return number_format($czk, 0, ',', ' ').' Kč';
    }

    /** @return Collection<int, Deal> */
    private function open(): Collection
    {
        return $this->open ??= Deal::query()->open()->get(['id', 'stage', 'value_czk', 'probability', 'expected_close_at']);
    }

    private function wonBetween(Carbon $from, Carbon $to): int
    {
        return (int) Deal::query()
            ->where('stage', DealStage::Won->value)
            ->whereBetween('won_at', [$from, $to])
            ->sum('value_czk');
    }

    private function monthLabel(Carbon $month): string
    {
        $names = [1 => 'led', 'úno', 'bře', 'dub', 'kvě', 'čvn', 'čvc', 'srp', 'zář', 'říj', 'lis', 'pro'];

        return $names[$month->month].($month->month === 1 ? ' '.$month->format('y') : '');
    }
}
