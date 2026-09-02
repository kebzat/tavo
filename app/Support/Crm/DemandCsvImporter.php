<?php

namespace App\Support\Crm;

use App\Enums\Crm\DemandSource;
use App\Enums\Crm\DemandStatus;
use App\Enums\Crm\Priority;

/**
 * Import otevřených poptávek z tabulky rešerše.
 *
 * Stejný soubor, ze kterého chodí prospekty, mívá druhý list s poptávkami
 * z portálů. Sloupce jsou pojmenované česky, proto se sem převádějí:
 * `Priorita, Zdroj, URL, Datum, Co chtějí, Odhad ceny, Stav, Datum reakce, Poznámka`.
 *
 * Vlastní upsert podle adresy pak dělá DemandImporter, tedy stejná cesta,
 * kterou přitéká ranní automatizace.
 */
class DemandCsvImporter
{
    /**
     * Pole poptávky a název sloupce, který se na ni napojí sám.
     *
     * @var array<string, array{label: string, csv: string}>
     */
    public const FIELDS = [
        'url' => ['label' => 'Odkaz na poptávku', 'csv' => 'url'],
        'title' => ['label' => 'Co chtějí', 'csv' => 'cochteji'],
        'source' => ['label' => 'Zdroj', 'csv' => 'zdroj'],
        'posted_at' => ['label' => 'Datum zveřejnění', 'csv' => 'datum'],
        'budget_estimate' => ['label' => 'Odhad ceny', 'csv' => 'odhadceny'],
        'priority' => ['label' => 'Priorita', 'csv' => 'priorita'],
        'status' => ['label' => 'Stav', 'csv' => 'stav'],
        'replied_at' => ['label' => 'Datum reakce', 'csv' => 'datumreakce'],
        'notes' => ['label' => 'Poznámka', 'csv' => 'poznamka'],
    ];

    public function __construct(private readonly CompanyCsvImporter $reader = new CompanyCsvImporter) {}

    /**
     * Načte soubor. Čtení i rozpoznání oddělovače je stejné jako u firem,
     * takže se používá tentýž kód.
     *
     * @return array{header: array<int, string>, rows: array<int, array<int, string>>}
     */
    public function read(string $path): array
    {
        return $this->reader->read($path);
    }

    /**
     * @param  array<int, string>  $header
     * @return array<string, int|null>
     */
    public function guessMapping(array $header): array
    {
        $normalized = [];

        foreach ($header as $index => $name) {
            $normalized[$this->normalizeHeader($name)] = $index;
        }

        $mapping = [];

        foreach (self::FIELDS as $field => $definition) {
            $mapping[$field] = $normalized[$definition['csv']] ?? null;
        }

        return $mapping;
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     * @param  array<string, int|null>  $mapping
     * @return array{created: int, updated: int, skipped: int}
     */
    public function import(array $rows, array $mapping): array
    {
        $payload = [];

        foreach ($rows as $row) {
            $url = $this->value($row, $mapping['url'] ?? null);

            // Bez adresy se poptávka nedá odlišit od ostatních. DemandImporter
            // takový řádek započítá do přeskočených.
            if ($url === null) {
                $payload[] = [];

                continue;
            }

            [$title, $summary] = $this->splitTitle($this->value($row, $mapping['title'] ?? null));

            $payload[] = [
                'url' => $url,
                'title' => $title,
                'summary' => $summary,
                'source' => DemandSource::fromCsv($this->value($row, $mapping['source'] ?? null))->value,
                'posted_at' => $this->value($row, $mapping['posted_at'] ?? null),
                'budget_estimate' => $this->value($row, $mapping['budget_estimate'] ?? null),
                'priority' => (Priority::tryFrom(mb_strtoupper((string) $this->value($row, $mapping['priority'] ?? null))) ?? Priority::B)->value,
                'status' => DemandStatus::fromCsv($this->value($row, $mapping['status'] ?? null))->value,
                'replied_at' => $this->value($row, $mapping['replied_at'] ?? null),
                'notes' => $this->value($row, $mapping['notes'] ?? null),
            ];
        }

        return (new DemandImporter)->import($payload);
    }

    /**
     * Rozdělení popisu na název a shrnutí.
     *
     * Rešerše píše poptávky ve tvaru „zadavatel – co chce" nebo
     * „co chce – upřesnění". Celý text jako název by ze seznamu udělal
     * čtyři řádky textu na položku, proto se ulomí první část.
     *
     * Pomlčka musí být obklopená mezerami, jinak by se rozpadl rozsah
     * v ceně („25–50k") i běžné spřežky.
     *
     * @return array{0: string, 1: ?string}
     */
    private function splitTitle(?string $text): array
    {
        $text = trim((string) $text);

        if ($text === '') {
            return ['Poptávka bez popisu', null];
        }

        foreach ([' – ', ' — ', ' - '] as $separator) {
            $position = mb_strpos($text, $separator);

            if ($position !== false && $position > 0) {
                return [
                    trim(mb_substr($text, 0, $position)),
                    trim(mb_substr($text, $position + mb_strlen($separator))) ?: null,
                ];
            }
        }

        return [$text, null];
    }

    private function value(array $row, ?int $index): ?string
    {
        if ($index === null) {
            return null;
        }

        $value = trim((string) ($row[$index] ?? ''));

        return in_array($value, ['', '-', '–', '—', '?', 'n/a', 'N/A'], true) ? null : $value;
    }

    /** Název sloupce bez diakritiky, mezer a interpunkce. */
    private function normalizeHeader(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = strtr($name, [
            'á' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e', 'í' => 'i',
            'ň' => 'n', 'ó' => 'o', 'ř' => 'r', 'š' => 's', 'ť' => 't', 'ú' => 'u',
            'ů' => 'u', 'ý' => 'y', 'ž' => 'z',
        ]);

        return preg_replace('~[^a-z0-9]~', '', $name) ?? $name;
    }
}
