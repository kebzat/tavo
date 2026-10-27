<?php

namespace App\Support\Automations;

use App\Models\Automation;
use App\Models\AutomationEvent;
use App\Support\Vault\VaultException;
use App\Support\Vault\ZaslatApi;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Throwable;

/**
 * Připomínky vyzvednutí zásilek (Upgates + Zaslat). Jeden běh:
 *
 * 1. stáhne stavy objednávek z exportu Upgates (UpgatesOrders), bez něj nic nemění,
 * 2. vezme aktivní zásilky ze Zaslatu,
 * 3. potvrdí čekající zprávy, jejichž objednávka už má cílový stav, a starší
 *    než 3 h označí jako chybu,
 * 4. pro zásilky na výdejním místě nebo vrácené založí zprávy (každou jen
 *    jednou), Upgates si je stáhne jako CSV import a stav změní sám,
 * 5. uloží přehled zásilek na výdejních místech (jen čísla objednávek a data).
 *
 * Stav objednávky mění jen ze `source_statuses`, takže nepřepíše ruční
 * práci (Vyřešeno, Stornováno…). Viz docs/AUTOMATIZACE.md.
 */
class ParcelPickup
{
    /** Za jak dlouho musí Upgates zprávu stáhnout a stav změnit, jinak je to chyba. */
    public const FAIL_AFTER_HOURS = 3;

    public function __construct(
        private ZaslatApi $zaslat,
        private UpgatesOrders $orders,
        private PacketaTracking $packeta,
        private AutomationAlerts $alerts,
    ) {}

    /**
     * @return array{created: list<array{order: string, kind: string, detail: string}>, confirmed: list<string>, failed: list<string>, parcels: list<array<string, mixed>>, warnings: list<string>, error: ?string}
     */
    public function run(Automation $automation, bool $dryRun = false): array
    {
        $report = ['created' => [], 'confirmed' => [], 'failed' => [], 'parcels' => [], 'warnings' => [], 'error' => null];

        if (! $automation->enabled) {
            $report['warnings'][] = 'Automatizace je vypnutá, nic se nezakládá.';

            return $report;
        }

        try {
            $export = $this->orders->load($automation, $dryRun);
            $report['warnings'] = $export['warnings'];
            $orders = array_map(fn (array $order): string => trim($order['status']), $export['orders']);
            $shipments = $this->shipments($automation);

            $this->settle($automation, $orders, $report, $dryRun);
            $this->plan($automation, $orders, $shipments, $report, $dryRun);
        } catch (AutomationException $e) {
            $report['error'] = $e->getMessage();
        } catch (Throwable $e) {
            // Do logu celá chyba, ven jen obecná zpráva: text výjimky může obsahovat adresu exportu s klíčem.
            report($e);
            $report['error'] = 'Neočekávaná chyba při běhu ('.class_basename($e).'). Podrobnosti jsou v logu webu.';
        }

        if ($dryRun) {
            return $report;
        }

        $automation->forceFill([
            'last_run_at' => now(),
            'last_error' => $report['error'],
            'last_warning' => $report['warnings'] ? implode(' ', $report['warnings']) : null,
            ...($report['error'] === null ? ['snapshot' => ['updated_at' => now()->toIso8601String(), 'parcels' => $report['parcels']]] : []),
        ])->save();

        if ($report['error'] !== null) {
            $this->alerts->problem($automation, 'error:'.sha1($report['error']), $report['error'],
                'Dokud chyba trvá, připomínky se neposílají. Nic se tím nerozbije, po opravě se běh sám chytí.');
        }

        if ($report['failed'] !== []) {
            $this->alerts->problem($automation, 'failed',
                'U '.count($report['failed']).' '.(count($report['failed']) === 1 ? 'objednávky' : 'objednávek').' ('.implode(', ', $report['failed']).') Upgates do '.self::FAIL_AFTER_HOURS.' hodin nezměnil stav, zpráva zákazníkovi tedy nejspíš neodešla.',
                'Zkontroluj v Upgates pravidelný import objednávek (Import objednávek, adresa CSV z nástrojů) a stav těch objednávek. Případně zprávu pošli ručně z objednávky.');
        }

        return $report;
    }

    /**
     * Aktivní zásilky ze Zaslatu, po 50 podle meta.total.
     *
     * @return list<array<string, mixed>>
     */
    private function shipments(Automation $automation): array
    {
        $entry = $automation->vaultEntry();

        if ($entry === null || ! ZaslatApi::supports($entry) || $entry->client_id !== $automation->client_id) {
            throw new AutomationException('Chybí přístup k Zaslatu (API klíč v trezoru u tohoto klienta).');
        }

        $shipments = [];
        $offset = 0;

        // Strop 100 stránek (5 000 zásilek), kdyby API vracelo nesmyslný počet.
        for ($page = 0; $page < 100; $page++) {
            try {
                $response = $this->zaslat->call($entry, 'GET', '/shipments/list', ['tracking' => '', 'status' => 'ACTIVE', 'offset' => $offset]);
            } catch (VaultException $e) {
                throw new AutomationException($e->getMessage());
            } catch (Throwable) {
                throw new AutomationException('Zaslat neodpovídá.');
            }

            if (! $response->successful()) {
                throw new AutomationException('Zaslat vrátil chybu (HTTP '.$response->status().'). '.mb_substr((string) $response->json('message'), 0, 200));
            }

            $data = (array) $response->json('data');

            foreach ($data as $tracking => $shipment) {
                if (is_array($shipment)) {
                    $shipments[] = $shipment + ['tracking_number' => (string) $tracking];
                }
            }

            $offset += max(1, (int) ($response->json('meta.limit') ?: 50));

            if ($data === [] || $offset >= (int) $response->json('meta.total')) {
                break;
            }
        }

        return $shipments;
    }

    /**
     * Čekající zprávy: cílový stav v exportu = potvrzeno. Objednávka mezitím
     * v jiném stavu (ručně vyřízená) = už neposílat. Starší než 3 h = chyba.
     *
     * @param  array<string, string>  $orders
     */
    private function settle(Automation $automation, array $orders, array &$report, bool $dryRun): void
    {
        $sources = $automation->sourceStatuses();

        foreach ($automation->events()->pending()->orderBy('id')->get() as $event) {
            $current = $orders[$event->order_number] ?? null;
            $changes = match (true) {
                $current === trim($event->target_status) => ['state' => AutomationEvent::STATE_CONFIRMED, 'confirmed_at' => now()],
                $current !== null && ! in_array($current, $sources, true) => ['state' => AutomationEvent::STATE_SKIPPED, 'detail' => 'Objednávka mezitím přešla do stavu „'.$current.'“, zpráva se už neposílá.'],
                $event->created_at->lt(now()->subHours(self::FAIL_AFTER_HOURS)) => ['state' => AutomationEvent::STATE_FAILED, 'detail' => $event->exported_at
                    ? 'Upgates si CSV stáhl, ale stav objednávky do '.self::FAIL_AFTER_HOURS.' hodin nezměnil.'
                    : 'Upgates si CSV do '.self::FAIL_AFTER_HOURS.' hodin nestáhl.'],
                default => null,
            };

            if ($changes === null) {
                continue;
            }

            match ($changes['state']) {
                AutomationEvent::STATE_CONFIRMED => $report['confirmed'][] = $event->order_number,
                AutomationEvent::STATE_FAILED => $report['failed'][] = $event->order_number,
                default => null,
            };

            if (! $dryRun) {
                $event->update($changes);
            }
        }
    }

    /**
     * @param  array<string, string>  $orders
     * @param  list<array<string, mixed>>  $shipments
     */
    private function plan(Automation $automation, array $orders, array $shipments, array &$report, bool $dryRun): void
    {
        $today = CarbonImmutable::now('Europe/Prague')->startOfDay();
        $events = $automation->events()->where('state', '!=', AutomationEvent::STATE_SKIPPED)->get()->groupBy('order_number');

        foreach ($shipments as $shipment) {
            $number = trim((string) ($shipment['reference'] ?? ''));
            $returned = (bool) data_get($shipment, 'tracking_information.returned', false);
            $atPickup = strtoupper((string) ($shipment['status'] ?? '')) === 'PARCELSHOP';

            if ($number === '' || (! $returned && ! $atPickup)) {
                continue;
            }

            $carrier = strtolower((string) ($shipment['carrier'] ?? '')) ?: null;
            $storedUntil = $atPickup && ! $returned ? $this->storedUntil($automation, $shipment, $carrier) : null;
            $daysLeft = $storedUntil ? (int) $today->diffInDays($storedUntil, false) : null;
            $sent = $events->get($number, collect())->pluck('kind')->all();
            $kind = $this->kind($automation, $orders[$number] ?? null, $returned, $daysLeft);

            if ($kind !== null && ! $this->alreadyCovered($kind, $sent) && trim((string) ($orders[$number] ?? '')) !== $this->target($automation, $kind)) {
                if ($this->create($automation, $number, $kind, $storedUntil, $carrier, $daysLeft, $report, $dryRun)) {
                    $sent[] = $kind;
                }
            }

            if ($atPickup) {
                $report['parcels'][] = [
                    'order' => $number,
                    'carrier' => $carrier,
                    'stored_until' => $storedUntil?->toDateString(),
                    'days_left' => $daysLeft,
                    'status' => $returned ? 'RETURNED' : 'PARCELSHOP',
                    'sent' => array_values(array_unique($sent)),
                ];
            }
        }

        usort($report['parcels'], fn (array $a, array $b): int => [$a['days_left'] ?? PHP_INT_MAX, $a['order']] <=> [$b['days_left'] ?? PHP_INT_MAX, $b['order']]);
    }

    /** Která zpráva teď platí. Pořadí důležitosti: vrácená > poslední den > připomínka. */
    private function kind(Automation $automation, ?string $current, bool $returned, ?int $daysLeft): ?string
    {
        if ($current === null || ! in_array($current, $automation->sourceStatuses(), true)) {
            return null;
        }

        return match (true) {
            $returned => $automation->setting('send_returned') ? AutomationEvent::KIND_RETURNED : null,
            $daysLeft === null => null,
            $daysLeft <= 0 => $automation->setting('send_last_day') ? AutomationEvent::KIND_LAST_DAY : null,
            $daysLeft <= (int) $automation->setting('remind_days_before') => $automation->setting('send_reminder') && $current === $automation->status('sent')
                ? AutomationEvent::KIND_REMINDER
                : null,
            default => null,
        };
    }

    /** Tahle nebo důležitější zpráva už u objednávky je. */
    private function alreadyCovered(string $kind, array $sent): bool
    {
        $rank = array_search($kind, AutomationEvent::KINDS, true);

        return collect($sent)->contains(fn (string $existing): bool => array_search($existing, AutomationEvent::KINDS, true) <= $rank);
    }

    private function target(Automation $automation, string $kind): string
    {
        return $automation->status(match ($kind) {
            AutomationEvent::KIND_REMINDER => 'reminder',
            AutomationEvent::KIND_LAST_DAY => 'last_day',
            default => 'returned',
        });
    }

    private function create(Automation $automation, string $number, string $kind, ?CarbonImmutable $storedUntil, ?string $carrier, ?int $daysLeft, array &$report, bool $dryRun): bool
    {
        $detail = match ($kind) {
            AutomationEvent::KIND_RETURNED => 'Zásilka se vrací do e-shopu.',
            AutomationEvent::KIND_LAST_DAY => 'Poslední den k vyzvednutí'.($storedUntil ? ' (do '.$storedUntil->format('j. n.').')' : '').'.',
            default => 'Na výdejním místě do '.$storedUntil?->format('j. n.').', '.match ($daysLeft) {
                1 => 'zbývá 1 den.',
                2, 3, 4 => 'zbývají '.$daysLeft.' dny.',
                default => 'zbývá '.$daysLeft.' dní.',
            },
        };

        $report['created'][] = ['order' => $number, 'kind' => $kind, 'detail' => $detail];

        if ($dryRun) {
            return true;
        }

        try {
            AutomationEvent::query()->create([
                'automation_id' => $automation->getKey(),
                'order_number' => $number,
                'kind' => $kind,
                'target_status' => $this->target($automation, $kind),
                'state' => AutomationEvent::STATE_PENDING,
                'stored_until' => $storedUntil?->toDateString(),
                'carrier' => $carrier,
                'detail' => $detail,
            ]);
        } catch (UniqueConstraintViolationException) {
            array_pop($report['created']);

            return false;
        }

        // Méně důležitá zpráva, která ještě čeká, se už neposílá (CSV má mít u objednávky jen jeden řádek).
        $automation->events()->pending()->where('order_number', $number)->where('kind', '!=', $kind)
            ->get()
            ->filter(fn (AutomationEvent $event): bool => $event->rank() > array_search($kind, AutomationEvent::KINDS, true))
            ->each(fn (AutomationEvent $event) => $event->update(['state' => AutomationEvent::STATE_SKIPPED, 'detail' => 'Nahradila ji důležitější zpráva.']));

        return true;
    }

    /**
     * Konec úložní doby. Zásilkovna ho ukazuje ve veřejném sledování, u
     * ostatních dopravců = první den na výdejním místě + úložní doba.
     */
    private function storedUntil(Automation $automation, array $shipment, ?string $carrier): ?CarbonImmutable
    {
        if (in_array($carrier, ['zasilkovna', 'packeta'], true) && filled($url = data_get($shipment, 'packages.0.carrier_tracking'))) {
            $date = $this->packeta->storedUntil((string) $url);

            if ($date !== null) {
                return CarbonImmutable::parse($date, 'Europe/Prague')->startOfDay();
            }
        }

        return $this->arrivedAt($shipment)?->addDays($automation->storageDays($carrier));
    }

    /** Den první události PARCELSHOP (události jsou po balících, nejnovější první). */
    private function arrivedAt(array $shipment): ?CarbonImmutable
    {
        $dates = [];

        foreach ((array) data_get($shipment, 'tracking_information.packages', []) as $package) {
            foreach (is_array($package) && isset($package['status']) ? [$package] : (array) $package as $event) {
                if (is_array($event) && strtoupper((string) ($event['status'] ?? '')) === 'PARCELSHOP' && preg_match('~^\d{4}-\d{2}-\d{2}~', (string) ($event['date'] ?? ''))) {
                    $dates[] = substr($event['date'], 0, 10);
                }
            }
        }

        return $dates === [] ? null : CarbonImmutable::parse(min($dates), 'Europe/Prague')->startOfDay();
    }
}
