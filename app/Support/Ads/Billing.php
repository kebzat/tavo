<?php

namespace App\Support\Ads;

use App\Models\Client;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Kolik za měsíc fakturovat: paušál plus hodiny nad rámec paušálu sazbou
 * z nastavení klienta. Nefakturovatelná práce se do hodin nepočítá,
 * jen do kapacity.
 */
final class Billing
{
    private function __construct(
        public readonly Client $client,
        public readonly CarbonImmutable $month,
        public readonly float $fee,
        public readonly float $includedHours,
        public readonly float $hourlyRate,
        public readonly float $billableHours,
        public readonly float $nonBillableHours,
        public readonly int $uninvoicedEntries,
    ) {}

    public static function for(Client $client, CarbonInterface $month): self
    {
        $month = CarbonImmutable::parse($month)->startOfMonth();
        $settings = $client->adSettings;
        $entries = $client->timeEntries()->inMonth($month->year, $month->month)->get();

        return new self(
            client: $client,
            month: $month,
            fee: (float) ($settings?->fee_czk ?? 0),
            includedHours: (float) ($settings?->included_hours ?? 0),
            hourlyRate: (float) ($settings?->hourly_rate ?? 0),
            billableHours: $entries->where('billable', true)->sum('minutes') / 60,
            nonBillableHours: $entries->where('billable', false)->sum('minutes') / 60,
            uninvoicedEntries: $entries->where('billable', true)->whereNull('invoiced_at')->count(),
        );
    }

    /** Hodiny nad paušál. */
    public function extraHours(): float
    {
        return max(0, $this->billableHours - $this->includedHours);
    }

    /** Kolik hodin z paušálu ještě zbývá. */
    public function remainingHours(): float
    {
        return max(0, $this->includedHours - $this->billableHours);
    }

    public function extraAmount(): float
    {
        return round($this->extraHours() * $this->hourlyRate);
    }

    public function total(): float
    {
        return $this->fee + $this->extraAmount();
    }

    /** Přesáhly hodiny nad paušál a chybí sazba? Pak částka navíc vychází nula a je to chyba v nastavení. */
    public function missingRate(): bool
    {
        return $this->extraHours() > 0 && $this->hourlyRate <= 0;
    }

    /** Označí fakturovatelné záznamy měsíce jako vyfakturované. */
    public function markInvoiced(): int
    {
        return $this->client->timeEntries()
            ->inMonth($this->month->year, $this->month->month)
            ->where('billable', true)
            ->whereNull('invoiced_at')
            ->update(['invoiced_at' => now()]);
    }

    /** @return array{fee: string, hours: string, extra: string, extra_amount: string, total: string, remaining: string} */
    public function formatted(): array
    {
        return [
            'fee' => Format::money($this->fee),
            'hours' => Format::number($this->billableHours, 1).' h'.($this->includedHours > 0 ? ' z '.Format::number($this->includedHours, 1).' h v paušálu' : ''),
            'extra' => Format::number($this->extraHours(), 1).' h',
            'extra_amount' => Format::money($this->extraAmount()),
            'total' => Format::money($this->total()),
            'remaining' => Format::number($this->remainingHours(), 1).' h',
        ];
    }

    /** Minuty ze zápisu typu „1:30“, „1,5“ nebo „90m“. */
    public static function parseMinutes(string $value): ?int
    {
        $value = trim(str_replace(',', '.', mb_strtolower($value)));

        return match (true) {
            (bool) preg_match('/^(\d+):(\d{1,2})$/', $value, $m) => (int) $m[1] * 60 + (int) $m[2],
            (bool) preg_match('/^(\d+)\s*(m|min)$/', $value, $m) => (int) $m[1],
            (bool) preg_match('/^(\d+(?:\.\d+)?)\s*h?$/', $value, $m) => (int) round((float) $m[1] * 60),
            default => null,
        };
    }

    /** „1:30 h“ z minut. */
    public static function formatMinutes(int $minutes): string
    {
        return intdiv($minutes, 60).':'.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT).' h';
    }
}
