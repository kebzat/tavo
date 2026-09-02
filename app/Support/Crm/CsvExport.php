<?php

namespace App\Support\Crm;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stažení dat jako CSV.
 *
 * Soubor se posílá s BOM a středníkem. Excel v české lokalizaci jinak otevře
 * všechny sloupce v jednom a diakritiku rozsype — a tenhle export se otevírá
 * hlavně v Excelu.
 */
class CsvExport
{
    /**
     * @param  array<int, string>  $header
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public static function download(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows): void {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $header, ';', '"', '\\');

            foreach ($rows as $row) {
                fputcsv($handle, array_map(self::stringify(...), $row), ';', '"', '\\');
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Enumy a data do buňky patří čitelně, ne jako objekt nebo ISO řetězec. */
    private static function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            $value instanceof \BackedEnum => method_exists($value, 'getLabel') ? $value->getLabel() : (string) $value->value,
            $value instanceof \DateTimeInterface => $value->format('j. n. Y'),
            is_bool($value) => $value ? 'ano' : 'ne',
            default => (string) $value,
        };
    }
}
