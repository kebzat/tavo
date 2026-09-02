<?php

namespace App\Support\Crm;

use App\Enums\Crm\CompanySegment;
use App\Enums\Crm\CompanySource;
use App\Enums\Crm\Priority;
use App\Models\Crm\Company;
use Illuminate\Support\Facades\DB;

/**
 * Import firem z tabulky rešerše.
 *
 * Rešerše vzniká v tabulkovém editoru a hlavička se občas změní, proto se
 * sloupce před importem mapují ručně. Předvolba sedí na náš obvyklý formát
 * `segment,firma,mesto,obor,web,platforma,bolest,balicek,reference,kontakt,priorita`,
 * takže v běžném případě stačí soubor nahrát a potvrdit.
 */
class CompanyCsvImporter
{
    /**
     * Pole karty firmy, do kterých umíme importovat, a název sloupce, který
     * se na ně automaticky napojí.
     *
     * @var array<string, array{label: string, csv: string}>
     */
    public const FIELDS = [
        'name' => ['label' => 'Název firmy', 'csv' => 'firma'],
        'segment' => ['label' => 'Segment', 'csv' => 'segment'],
        'city' => ['label' => 'Město', 'csv' => 'mesto'],
        'industry' => ['label' => 'Obor', 'csv' => 'obor'],
        'website' => ['label' => 'Web', 'csv' => 'web'],
        'platform' => ['label' => 'Platforma', 'csv' => 'platforma'],
        'pain' => ['label' => 'Bolest', 'csv' => 'bolest'],
        'offer' => ['label' => 'Nabídka / balíček', 'csv' => 'balicek'],
        'reference_to_use' => ['label' => 'Reference', 'csv' => 'reference'],
        'priority' => ['label' => 'Priorita', 'csv' => 'priorita'],
        'contact' => ['label' => 'Kontakt (e-mail, telefon)', 'csv' => 'kontakt'],
    ];

    /**
     * Načte soubor. Vrací hlavičku zvlášť, ať se z ní dá postavit mapování.
     *
     * @return array{header: array<int, string>, rows: array<int, array<int, string>>}
     */
    public function read(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return ['header' => [], 'rows' => []];
        }

        $lines = [];

        while (($line = fgets($handle)) !== false) {
            $lines[] = $line;
        }

        fclose($handle);

        if ($lines === []) {
            return ['header' => [], 'rows' => []];
        }

        // Excel v češtině ukládá CSV se středníkem, i když ho tak nikdo nenazve.
        // Oddělovač proto hádáme z hlavičky, místo abychom import odmítli.
        $delimiter = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';

        $rows = [];

        foreach ($lines as $index => $line) {
            // BOM z Excelu by se jinak přilepil k prvnímu názvu sloupce
            // a mapování by ho nenašlo.
            if ($index === 0) {
                $line = preg_replace('~^\xEF\xBB\xBF~', '', $line);
            }

            $parsed = str_getcsv(rtrim($line, "\r\n"), $delimiter, '"', '\\');

            // Prázdné řádky na konci souboru nejsou data.
            if ($parsed === [null] || $parsed === ['']) {
                continue;
            }

            $rows[] = array_map(fn ($value) => trim((string) $value), $parsed);
        }

        $header = array_shift($rows) ?? [];

        return ['header' => $header, 'rows' => $rows];
    }

    /**
     * Napojení polí karty na sloupce souboru. Klíč je pole karty, hodnota
     * index sloupce v CSV — nebo null, když se takový sloupec nenašel.
     *
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
     * Import. Vrací souhrn, který stránka ukáže uživateli — včetně jmen
     * přeskočených firem, ať je vidět, co se nenaimportovalo a proč.
     *
     * @param  array<int, array<int, string>>  $rows
     * @param  array<string, int|null>  $mapping
     * @return array{created: int, skipped: int, invalid: int, duplicates: array<int, string>}
     */
    public function import(array $rows, array $mapping, ?int $ownerId = null): array
    {
        $created = 0;
        $invalid = 0;
        $duplicates = [];

        // Duplicita se hlídá i uvnitř souboru, ne jen proti databázi — rešerše
        // často obsahuje tutéž firmu dvakrát pod jiným názvem.
        $seenDomains = [];

        foreach ($rows as $row) {
            $name = $this->value($row, $mapping['name'] ?? null);

            if ($name === null) {
                $invalid++;

                continue;
            }

            $website = $this->value($row, $mapping['website'] ?? null);
            $domain = Domain::normalize($website);

            if ($domain !== null && (in_array($domain, $seenDomains, true) || Company::where('domain', $domain)->exists())) {
                $duplicates[] = $name.' ('.$domain.')';

                continue;
            }

            if ($domain !== null) {
                $seenDomains[] = $domain;
            }

            DB::transaction(function () use ($row, $mapping, $name, $website, $ownerId): void {
                $company = Company::create([
                    'name' => $name,
                    'website' => $website,
                    'city' => $this->value($row, $mapping['city'] ?? null),
                    'industry' => $this->value($row, $mapping['industry'] ?? null),
                    'platform' => $this->value($row, $mapping['platform'] ?? null),
                    'pain' => $this->value($row, $mapping['pain'] ?? null),
                    'offer' => $this->value($row, $mapping['offer'] ?? null),
                    'reference_to_use' => $this->value($row, $mapping['reference_to_use'] ?? null),
                    'segment' => CompanySegment::fromCsv($this->value($row, $mapping['segment'] ?? null)),
                    'priority' => Priority::tryFrom(mb_strtoupper((string) $this->value($row, $mapping['priority'] ?? null))) ?? Priority::B,
                    'source' => CompanySource::Research,
                    'owner_id' => $ownerId,
                ]);

                $this->attachContact($company, $this->value($row, $mapping['contact'] ?? null));
            });

            $created++;
        }

        return [
            'created' => $created,
            'skipped' => count($duplicates),
            'invalid' => $invalid,
            'duplicates' => $duplicates,
        ];
    }

    /**
     * Založí kontakt, pokud je v textu na koho se obrátit.
     *
     * Rešerše často žádný kontakt nenajde a napíše do sloupce „na webu" nebo
     * „nešlo ověřit". Kontaktní karta bez jména, e-mailu i telefonu by byla
     * jen prázdný řádek, takže se v takovém případě nezakládá a text se uloží
     * k firmě jako poznámka. Informace „kontakt je jen přes formulář" se tím
     * neztratí a v kontaktech nezůstane balast.
     */
    private function attachContact(Company $company, ?string $text): void
    {
        $contact = $this->parseContact($text);

        if ($contact === null) {
            return;
        }

        if ($contact['name'] === null && $contact['email'] === null && $contact['phone'] === null) {
            $company->forceFill([
                'notes' => trim($company->notes."\nKontakt: ".trim((string) $text)),
            ])->save();

            return;
        }

        $company->contacts()->create($contact + ['is_primary' => true]);
    }

    /**
     * Rozpad volného textu „info@firma.cz, +420 777 123 456 (Jan Novák)"
     * na kontaktní kartu.
     *
     * @return array{name: ?string, email: ?string, phone: ?string, notes: ?string}|null
     */
    public function parseContact(?string $text): ?array
    {
        $text = trim((string) $text);

        if ($text === '') {
            return null;
        }

        $rest = $text;

        // Jméno bereme jen ze závorky. Volný text před e-mailem bývá spíš
        // popis („objednávky") než jméno člověka a v kartě by mátl.
        $name = null;

        if (preg_match('~\(([^)]+)\)~u', $rest, $match)) {
            $name = trim($match[1]);
            $rest = str_replace($match[0], ' ', $rest);
        }

        $email = null;

        if (preg_match('~[\w.+-]+@[\w-]+\.[\w.-]+~u', $rest, $match)) {
            $email = rtrim($match[0], '.,;');
            $rest = str_replace($match[0], ' ', $rest);
        }

        $phone = null;

        // Předvolba je volitelná, mezery uvnitř čísla libovolné — v rešerši
        // se vyskytuje „+420 777 123 456" i „777123456".
        if (preg_match('~(\+420[\s\x{00A0}]*)?(\d[\s\x{00A0}]*){9}~u', $rest, $match)) {
            $phone = preg_replace('~[\s\x{00A0}]+~u', ' ', trim($match[0]));
            $rest = str_replace($match[0], ' ', $rest);
        }

        $notes = trim(preg_replace('~[\s,;]+~u', ' ', $rest));

        if ($name === null) {
            // Ať je v kartě vidět, že jméno neznáme, protože firma přišla
            // z rešerše — ne proto, že ho někdo zapomněl vyplnit.
            $notes = trim('(kontakt z rešerše) '.$notes);
        }

        return [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'notes' => $notes !== '' ? $notes : null,
        ];
    }

    /**
     * Hodnota buňky, nebo null u prázdné a nenamapované.
     *
     * Rešerše se píše ručně, takže neznámý údaj v ní bývá „-" nebo „?".
     * Uložit takový znak jako platformu by znamenal filtr „Platforma: ?"
     * v seznamu firem.
     */
    private function value(array $row, ?int $index): ?string
    {
        if ($index === null) {
            return null;
        }

        $value = trim((string) ($row[$index] ?? ''));

        if (in_array($value, ['', '-', '–', '—', '?', 'n/a', 'N/A'], true)) {
            return null;
        }

        return $value;
    }

    /** Název sloupce bez diakritiky a malými písmeny — „Město" najde „mesto". */
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
