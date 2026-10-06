<?php

namespace App\Support;

use App\Filament\Tools\Resources\Clients\ClientResource;
use App\Models\Client;
use App\Models\ClientRetainer;
use App\Support\Ads\Format;
use App\Support\Crm\MoneyForecast;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Výhled pravidelných příjmů: kolik který klient platí v příštích měsících
 * podle paušálů s daty Od a Do. Předběžné částky zvlášť, ať je vidět,
 * co je domluvené a co jen plán.
 *
 * Hodiny nad paušál ani jednorázové zakázky sem nepatří, dopředu je
 * neznáme. Rozjednané obchody má Přehled.
 */
final class RetainerOutlook
{
    /** @var list<CarbonImmutable> */
    public readonly array $months;

    /** @var Collection<int, Client> */
    private Collection $clients;

    public function __construct(int $count = 12, ?CarbonImmutable $from = null)
    {
        $from = ($from ?? CarbonImmutable::now())->startOfMonth();
        $this->months = array_map(fn (int $offset): CarbonImmutable => $from->addMonthsNoOverflow($offset), range(0, $count - 1));

        $this->clients = Client::query()
            ->active()
            ->with(['retainers', 'adSettings'])
            ->where(fn ($query) => $query
                ->whereHas('retainers')
                ->orWhereHas('adSettings', fn ($query) => $query->where('fee_czk', '>', 0)))
            ->orderBy('name')
            ->get();
    }

    /**
     * Klient po měsících. Bez rozdělení paušálu po oblastech se bere paušál
     * z nastavení reklam, stejně jako ve Fakturaci, a platí bez konce.
     *
     * @return array{confirmed: int, tentative: int}
     */
    private function amount(Client $client, CarbonImmutable $month): array
    {
        if ($client->retainers->isEmpty()) {
            return ['confirmed' => (int) ($client->adSettings?->fee_czk ?? 0), 'tentative' => 0];
        }

        $active = $client->retainers->filter(fn (ClientRetainer $retainer): bool => $retainer->activeIn($month));

        return [
            'confirmed' => (int) $active->where('is_tentative', false)->sum('monthly_fee'),
            'tentative' => (int) $active->where('is_tentative', true)->sum('monthly_fee'),
        ];
    }

    /** @return list<array{label: string, short: string, current: bool}> */
    public function monthHeaders(): array
    {
        return array_map(fn (CarbonImmutable $month): array => [
            'label' => $month->translatedFormat('F Y'),
            'short' => self::shortMonth($month),
            'current' => $month->isSameMonth(CarbonImmutable::now()),
        ], $this->months);
    }

    /**
     * Řádky tabulky: klient a částka v každém měsíci. Klienti, kteří ve výhledu
     * nic neplatí (paušál skončil dřív), vypadnou.
     *
     * @return Collection<int, array{name: string, url: string, note: ?string, cells: list<array{amount: string, tentative: bool, empty: bool, title: string}>}>
     */
    public function rows(): Collection
    {
        return $this->clients
            ->map(function (Client $client): array {
                $amounts = array_map(fn (CarbonImmutable $month): array => $this->amount($client, $month), $this->months);

                return [
                    'name' => $client->name,
                    'url' => ClientResource::getUrl('edit', ['record' => $client]),
                    'note' => $this->endNote($amounts),
                    'any' => collect($amounts)->contains(fn (array $amount): bool => $amount['confirmed'] + $amount['tentative'] > 0),
                    'cells' => array_map(fn (array $amount): array => [
                        'amount' => $amount['confirmed'] + $amount['tentative'] > 0 ? MoneyForecast::short($amount['confirmed'] + $amount['tentative']) : '–',
                        'tentative' => $amount['tentative'] > 0,
                        'empty' => $amount['confirmed'] + $amount['tentative'] === 0,
                        'title' => Format::money($amount['confirmed']).' domluveno'
                            .($amount['tentative'] > 0 ? ', '.Format::money($amount['tentative']).' předběžně' : ''),
                    ], $amounts),
                ];
            })
            ->filter(fn (array $row): bool => $row['any'])
            ->values();
    }

    /**
     * Součty po měsících a sloupce grafu. Výšky jsou poměr k nejvyššímu měsíci.
     *
     * @return list<array{short: string, current: bool, confirmed: string, tentative: string, total: string, top: string, diff: ?string, down: bool, confirmed_height: int, tentative_height: int}>
     */
    public function totals(): array
    {
        $sums = $this->sums();
        $max = max(1, ...array_map(fn (array $sum): int => $sum['confirmed'] + $sum['tentative'], $sums));
        $base = $sums[0]['confirmed'];

        return array_map(function (array $sum, int $index) use ($max, $base): array {
            $total = $sum['confirmed'] + $sum['tentative'];
            $diff = $sum['confirmed'] - $base;

            return [
                'short' => self::shortMonth($this->months[$index]),
                'current' => $index === 0,
                'confirmed' => Format::money($sum['confirmed']),
                'tentative' => Format::money($sum['tentative']),
                'total' => Format::money($total),
                'top' => MoneyForecast::short($total),
                'diff' => $index > 0 && $diff !== 0 ? ($diff > 0 ? '+' : '−').MoneyForecast::short(abs($diff)) : null,
                'down' => $diff < 0,
                'confirmed_height' => (int) round($sum['confirmed'] / $max * 100),
                'tentative_height' => (int) round($sum['tentative'] / $max * 100),
            ];
        }, $sums, array_keys($sums));
    }

    /**
     * Tři čísla nahoře: kolik chodí teď, průměr výhledu a nejslabší měsíc
     * s tím, kolik do dnešní úrovně chybí.
     *
     * @return array{now: string, average: string, weakest_label: string, weakest: string, gap: ?string}
     */
    public function summary(): array
    {
        $sums = $this->sums();
        $confirmed = array_column($sums, 'confirmed');
        $weakestIndex = array_keys($confirmed, min($confirmed))[0];
        $gap = $confirmed[0] - $confirmed[$weakestIndex];

        return [
            'now' => Format::money($confirmed[0]),
            'average' => Format::money(array_sum($confirmed) / count($confirmed)),
            'weakest_label' => $this->months[$weakestIndex]->translatedFormat('F Y'),
            'weakest' => Format::money($confirmed[$weakestIndex]),
            'gap' => $gap > 0 ? Format::money($gap) : null,
        ];
    }

    /** @return list<array{confirmed: int, tentative: int}> */
    private function sums(): array
    {
        return array_map(fn (CarbonImmutable $month): array => $this->clients->reduce(function (array $carry, Client $client) use ($month): array {
            $amount = $this->amount($client, $month);

            return ['confirmed' => $carry['confirmed'] + $amount['confirmed'], 'tentative' => $carry['tentative'] + $amount['tentative']];
        }, ['confirmed' => 0, 'tentative' => 0]), $this->months);
    }

    /**
     * První změna domluvené částky ve výhledu: „Domluveno do prosince 2026“,
     * nebo „Od ledna 2027: 10 tis. místo 30 tis.“
     *
     * @param  list<array{confirmed: int, tentative: int}>  $amounts
     */
    private function endNote(array $amounts): ?string
    {
        foreach ($amounts as $index => $amount) {
            $before = $index > 0 ? $amounts[$index - 1]['confirmed'] : null;

            if ($before === null || $amount['confirmed'] === $before) {
                continue;
            }

            return $amount['confirmed'] === 0
                ? 'Domluveno do '.self::monthGenitive($this->months[$index - 1])
                : 'Od '.self::monthGenitive($this->months[$index]).': '.MoneyForecast::short($amount['confirmed']).' místo '.MoneyForecast::short($before);
        }

        return null;
    }

    private static function shortMonth(CarbonImmutable $month): string
    {
        $names = [1 => 'led', 'úno', 'bře', 'dub', 'kvě', 'čvn', 'čvc', 'srp', 'zář', 'říj', 'lis', 'pro'];

        return $names[$month->month].($month->month === 1 ? ' '.$month->format('y') : '');
    }

    private static function monthGenitive(CarbonImmutable $month): string
    {
        $names = [1 => 'ledna', 'února', 'března', 'dubna', 'května', 'června', 'července', 'srpna', 'září', 'října', 'listopadu', 'prosince'];

        return $names[$month->month].' '.$month->year;
    }
}
