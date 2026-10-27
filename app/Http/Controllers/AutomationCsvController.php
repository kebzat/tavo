<?php

namespace App\Http\Controllers;

use App\Models\Automation;
use App\Models\AutomationEvent;
use Illuminate\Http\Response;

/**
 * CSV pro pravidelný import objednávek v Upgates: číslo objednávky a nový
 * stav, Upgates pak pošle e-mail nebo SMS navázané na ten stav. Bez
 * přihlášení, Upgates stahuje z adresy s tokenem. Kódování Windows-1250,
 * středník, CRLF (tak Upgates import čte). Vypnutá automatizace = jen hlavička.
 */
class AutomationCsvController extends Controller
{
    public const HEADER = '[ORDER_NUMBER];[ORDER_STATUS]';

    public function __invoke(string $token): Response
    {
        $automation = Automation::query()->where('import_token', $token)->first();

        abort_if($automation === null || ! hash_equals($automation->import_token, $token), 404);

        $lines = [self::HEADER];

        if ($automation->enabled) {
            $events = $automation->events()->pending()->orderBy('id')->get();

            foreach ($events as $event) {
                $lines[] = self::cell($event->order_number).';'.self::cell($event->target_status);
            }

            // Kdy si Upgates řádek poprvé stáhl (pozná se tak, jestli import vůbec běží).
            AutomationEvent::query()->whereIn('id', $events->pluck('id'))->whereNull('exported_at')->update(['exported_at' => now()]);
        }

        $csv = implode("\r\n", $lines)."\r\n";

        return response(iconv('UTF-8', 'Windows-1250//TRANSLIT', $csv), 200, [
            'Content-Type' => 'text/csv; charset=windows-1250',
            'Content-Disposition' => 'inline; filename="upgates-import.csv"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /** Středník ani konec řádku v buňce nesmí rozbít sloupce. */
    private static function cell(string $value): string
    {
        $value = trim(str_replace(["\r", "\n"], ' ', $value));

        return str_contains($value, ';') || str_contains($value, '"') ? '"'.str_replace('"', '""', $value).'"' : $value;
    }
}
