<?php

namespace App\Support\Automations;

use App\Models\Automation;
use App\Models\AutomationEvent;
use Illuminate\Support\Collection;

/**
 * CSV pro import objednávek v Upgates: číslo objednávky a nový stav, Upgates
 * pak pošle e-mail nebo SMS navázané na ten stav. Kódování Windows-1250,
 * středník, CRLF (tak Upgates import čte). Vypnutá automatizace = jen hlavička.
 * Opakovaný import se stejným stavem nic nepošle (ověřeno 27. 10. 2026),
 * čekající řádky proto v souboru zůstávají, dokud je export nepotvrdí.
 */
class ImportCsv
{
    public const HEADER = '[ORDER_NUMBER];[ORDER_STATUS]';

    /** @return Collection<int, AutomationEvent> */
    public static function rows(Automation $automation): Collection
    {
        return $automation->enabled ? $automation->events()->pending()->orderBy('id')->get() : collect();
    }

    /** @param  Collection<int, AutomationEvent>  $rows */
    public static function build(Collection $rows): string
    {
        $lines = [self::HEADER, ...$rows->map(fn (AutomationEvent $event): string => self::cell($event->order_number).';'.self::cell($event->target_status))];

        return iconv('UTF-8', 'Windows-1250//TRANSLIT', implode("\r\n", $lines)."\r\n");
    }

    /** Kdy řádek poprvé odešel do Upgates (pozná se tak, jestli import vůbec běží). */
    public static function markExported(Collection $rows): void
    {
        AutomationEvent::query()->whereIn('id', $rows->pluck('id'))->whereNull('exported_at')->update(['exported_at' => now()]);
    }

    /** Středník ani konec řádku v buňce nesmí rozbít sloupce. */
    private static function cell(string $value): string
    {
        $value = trim(str_replace(["\r", "\n"], ' ', $value));

        return str_contains($value, ';') || str_contains($value, '"') ? '"'.str_replace('"', '""', $value).'"' : $value;
    }
}
