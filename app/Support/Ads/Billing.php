<?php

namespace App\Support\Ads;

use App\Enums\WorkArea;
use App\Models\Client;
use App\Models\ClientInvoice;
use App\Models\ClientRetainer;
use App\Models\TimeEntry;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use LogicException;

/**
 * Kolik za měsíc fakturovat: paušál plus hodiny nad rámec paušálu sazbou
 * z nastavení klienta. Nefakturovatelná práce se do hodin nepočítá,
 * jen do kapacity.
 *
 * Paušál se bere z rozdělení po oblastech (client_retainers), když ho klient
 * má, jinak z nastavení reklam. Bez hodin v paušálu se nad rámec neúčtuje.
 *
 * Předběžný paušál se nefakturuje. Když na něj dojde měsíc a pořád je
 * předběžný, Fakturace ho ukáže jako „nepotvrzeno“, ať nezapadne.
 *
 * Vyfakturovaný měsíc drží client_invoices po oblastech, i když klient nemá
 * zapsané hodiny. Hodiny dopsané po vyfakturování se ukážou jako „dopsáno po faktuře“.
 */
final class Billing
{
    /** @param  list<int>  $entryIds  fakturovatelné záznamy hodin, které do výpočtu patří */
    private function __construct(
        public readonly Client $client,
        public readonly CarbonImmutable $month,
        public readonly float $fee,
        public readonly float $includedHours,
        public readonly float $hourlyRate,
        public readonly float $billableHours,
        public readonly float $nonBillableHours,
        public readonly int $uninvoicedEntries,
        public readonly bool $capped = true,
        public readonly ?ClientInvoice $invoice = null,
        public readonly float $tentativeFee = 0,
        public readonly ?WorkArea $area = null,
        public readonly array $entryIds = [],
        public readonly float $unassignedHours = 0,
    ) {}

    /**
     * Bez oblasti celý klient dohromady (detail klienta v reklamách).
     * S oblastí jen její paušál a hodiny, tak jak se fakturuje: vývoj
     * a marketing zvlášť, viz users.billing_area.
     */
    public static function for(Client $client, CarbonInterface $month, ?WorkArea $area = null): self
    {
        $month = CarbonImmutable::parse($month)->startOfMonth();
        $settings = $client->adSettings;
        $billed = $client->retainers->filter(fn (ClientRetainer $retainer): bool => $retainer->billedIn($month));
        $retainers = $area ? $billed->filter(fn (ClientRetainer $retainer): bool => $retainer->area === $area) : $billed;
        $tentative = $client->retainers->filter(fn (ClientRetainer $retainer): bool => $retainer->is_tentative
            && $retainer->activeIn($month)
            && ($area === null || $retainer->area === $area));

        // Paušál z nastavení reklam platí, jen když klient nemá paušál po oblastech.
        // Reklamy dělá marketing, proto patří tam.
        $usesAdsFee = $billed->isEmpty() && ($area === null || $area === WorkArea::Marketing);

        $areas = self::areasOf($client, $month);
        $all = $client->timeEntries()->with('task')->inMonth($month->year, $month->month)->get();
        $entries = $area ? $all->filter(fn (TimeEntry $entry): bool => self::areaOf($entry, $areas) === $area) : $all;
        $billable = $entries->where('billable', true);

        return new self(
            client: $client,
            month: $month,
            fee: $retainers->isNotEmpty() ? (float) $retainers->sum('monthly_fee') : ($usesAdsFee ? (float) ($settings?->fee_czk ?? 0) : 0),
            includedHours: $retainers->isNotEmpty() ? (float) $retainers->sum('included_hours') : ($usesAdsFee ? (float) ($settings?->included_hours ?? 0) : 0),
            hourlyRate: (float) ($settings?->hourly_rate ?? 0),
            billableHours: $billable->sum('minutes') / 60,
            nonBillableHours: $entries->where('billable', false)->sum('minutes') / 60,
            uninvoicedEntries: $billable->whereNull('invoiced_at')->count(),
            capped: $retainers->isEmpty() || $retainers->contains(fn (ClientRetainer $retainer): bool => $retainer->included_hours !== null),
            invoice: $area ? $client->invoices()->whereDate('month', $month->toDateString())->where('area', $area->value)->first() : null,
            tentativeFee: (float) $tentative->sum('monthly_fee'),
            area: $area,
            entryIds: $billable->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            unassignedHours: $all->where('billable', true)->filter(fn (TimeEntry $entry): bool => self::areaOf($entry, $areas) === null)->sum('minutes') / 60,
        );
    }

    /**
     * Oblasti, ve kterých klient v měsíci platí paušál. Klient jen s paušálem
     * z reklam platí marketing.
     *
     * @return list<WorkArea>
     */
    public static function areasOf(Client $client, CarbonInterface $month): array
    {
        $areas = $client->retainers
            ->filter(fn (ClientRetainer $retainer): bool => $retainer->billedIn($month))
            ->map(fn (ClientRetainer $retainer): WorkArea => $retainer->area)
            ->unique()
            ->values()
            ->all();

        return $areas === [] && ($client->adSettings?->fee_czk ?? 0) > 0 ? [WorkArea::Marketing] : $areas;
    }

    /**
     * Oblast zápisu: z úkolu, ze zápisu, a když nic, tak jediná oblast
     * klienta. U klienta s webem i marketingem zůstane bez oblasti.
     *
     * @param  list<WorkArea>  $areas
     */
    public static function areaOf(TimeEntry $entry, array $areas): ?WorkArea
    {
        return $entry->task?->area ?? $entry->area ?? (count($areas) === 1 ? $areas[0] : null);
    }

    /** Hodiny nad paušál. Paušál bez stropu hodin žádné nemá. */
    public function extraHours(): float
    {
        return $this->capped ? max(0, $this->billableHours - $this->includedHours) : 0;
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

    public function isInvoiced(): bool
    {
        return $this->invoice !== null;
    }

    /** Vyfakturováno, ale pak přibyly hodiny nebo se změnil paušál. */
    public function changedSinceInvoice(): bool
    {
        return $this->invoice !== null
            && ($this->uninvoicedEntries > 0 || (int) round($this->total()) !== $this->invoice->amount_czk);
    }

    /**
     * Označí oblast v měsíci jako vyfakturovanou na současnou částku, i se
     * zapsanými hodinami. Opakované označení částku přepíše, třeba po
     * dopsaných hodinách.
     *
     * @return int kolik záznamů hodin se nově označilo
     */
    public function markInvoiced(?int $userId = null): int
    {
        if ($this->area === null) {
            throw new LogicException('Fakturuje se po oblastech, Billing::for() potřebuje oblast.');
        }

        ClientInvoice::query()->updateOrCreate(
            ['client_id' => $this->client->getKey(), 'month' => $this->month->toDateString(), 'area' => $this->area->value],
            ['amount_czk' => (int) round($this->total()), 'invoiced_at' => now(), 'user_id' => $userId],
        );

        return TimeEntry::query()->whereKey($this->entryIds)->whereNull('invoiced_at')->update(['invoiced_at' => now()]);
    }

    /** Zpět, když se kliklo omylem. Hodiny oblasti zase čekají na fakturu. */
    public function unmarkInvoiced(): void
    {
        $this->invoice?->delete();

        TimeEntry::query()->whereKey($this->entryIds)->update(['invoiced_at' => null]);
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
