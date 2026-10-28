<?php

namespace App\Support\Automations;

use App\Models\Automation;
use DOMElement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;
use XMLReader;

/**
 * Aktuální stavy objednávek z XML exportů Upgates (číslo → stav). Z exportu
 * se čte jen číslo objednávky, stav a čas poslední změny, u objednávek
 * s dárkovým balíčkem navíc obsah balíčku z poznámky (GiftBoxes) a čas
 * vytvoření. Osobní údaje zákazníků se nikam neukládají.
 *
 * Při víc adresách je první kompletní export (tisíce objednávek): stahuje se
 * nejvýš jednou za 6 h a mezi tím se drží v cache na disku (jen čísla,
 * stavy a časy). Ostatní (změny za 24 h) se stahují při každém běhu. Pro
 * každé číslo vyhraje záznam s novějším UPDATED_AT, bez něj pozdější adresa.
 */
class UpgatesOrders
{
    /** Jak často stahovat kompletní export. */
    public const FULL_REFRESH_HOURS = 6;

    /** Jak starý kompletní export z cache ještě stačí, když stažení selže. */
    public const FULL_MAX_AGE_HOURS = 30;

    /** Verze cache kompletního exportu; zvýšit, když parse() začne číst něco nového. */
    private const CACHE_VERSION = 2;

    /**
     * @return array{orders: array<string, array<string, mixed>>, warnings: list<string>}
     */
    public function load(Automation $automation, bool $dryRun = false): array
    {
        $urls = $automation->exportUrls();

        if ($urls === []) {
            throw new AutomationException('Chybí adresa exportu objednávek z Upgates, bez ní nejde zjistit stav objednávek.');
        }

        $warnings = [];
        $sources = [];

        if (count($urls) > 1) {
            $sources[] = $this->full($automation, array_shift($urls), $dryRun, $warnings);
        }

        foreach ($urls as $url) {
            $sources[] = $this->fetch($url);
        }

        return ['orders' => self::merge($sources), 'warnings' => $warnings];
    }

    /**
     * Sloučí exporty v pořadí adres. Novější UPDATED_AT vyhraje, bez času
     * (nebo při shodě) vyhraje pozdější export.
     *
     * @param  list<array<string, array{status: string, updated_at: ?string}>>  $sources
     * @return array<string, array{status: string, updated_at: ?string}>
     */
    public static function merge(array $sources): array
    {
        $orders = [];

        foreach ($sources as $source) {
            foreach ($source as $number => $order) {
                $number = (string) $number;
                $known = $orders[$number] ?? null;

                if ($known && $known['updated_at'] && $order['updated_at'] && strtotime($order['updated_at']) < strtotime($known['updated_at'])) {
                    continue;
                }

                $orders[$number] = $order;
            }
        }

        return $orders;
    }

    /**
     * Kompletní export: z cache, když je mladší než 6 h, jinak stáhnout.
     * Když stažení selže a cache je mladší než 30 h, poběží se s ní a jen
     * se varuje.
     *
     * @param  list<string>  $warnings
     * @return array<string, array{status: string, updated_at: ?string}>
     */
    private function full(Automation $automation, string $url, bool $dryRun, array &$warnings): array
    {
        $path = 'automatizace/'.$automation->getKey().'/objednavky.json';
        $cache = Storage::disk('local')->exists($path) ? json_decode((string) Storage::disk('local')->get($path), true) : null;
        $cached = is_array($cache) && ($cache['url'] ?? null) === sha1($url) && ($cache['v'] ?? 1) === self::CACHE_VERSION ? $cache : null;
        $age = $cached ? Carbon::parse($cached['fetched_at'])->diffInMinutes(now()) / 60 : null;

        if ($cached && $age < self::FULL_REFRESH_HOURS) {
            return $cached['orders'];
        }

        try {
            $orders = $this->fetch($url);
        } catch (AutomationException $e) {
            if ($cached && $age < self::FULL_MAX_AGE_HOURS) {
                $warnings[] = 'Kompletní export objednávek se nepodařilo stáhnout ('.$e->getMessage().'), použil se uložený z '
                    .Carbon::parse($cached['fetched_at'])->timezone('Europe/Prague')->format('j. n. G:i').'.';

                return $cached['orders'];
            }

            throw $e;
        }

        if (! $dryRun) {
            Storage::disk('local')->put($path, json_encode(['v' => self::CACHE_VERSION, 'url' => sha1($url), 'fetched_at' => now()->toIso8601String(), 'orders' => $orders]));
        }

        return $orders;
    }

    /** @return array<string, array{status: string, updated_at: ?string}> */
    public function fetch(string $url): array
    {
        if (! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['https', 'http'], true)) {
            throw new AutomationException('Adresa exportu objednávek musí začínat https://.');
        }

        try {
            $response = Http::timeout(120)->withUserAgent('TAVEO automatizace')->get($url);
        } catch (Throwable) {
            throw new AutomationException('Export objednávek z Upgates neodpovídá.');
        }

        if (! $response->successful()) {
            throw new AutomationException('Export objednávek z Upgates vrátil chybu (HTTP '.$response->status().').');
        }

        return self::parse($response->body());
    }

    /**
     * Čísla objednávek a jejich stavy z XML Upgates. Číslo je v ORDER_NUMBER
     * (vlastní export) nebo HEADER/CODE (vzorový formát), stav v STATUS
     * přímo v objednávce nebo v HEADER (ne STATUS souhlasů). Položky s dárkovým
     * balíčkem v poznámce (ITEMS/ITEM/NOTE) přidají `boxes` a `created_at`.
     *
     * @return array<string, array{status: string, updated_at: ?string, created_at?: ?string, boxes?: list<array<string, mixed>>}>
     */
    public static function parse(string $xml): array
    {
        if (! str_contains($xml, '<ORDERS')) {
            throw new AutomationException('Export objednávek není XML z Upgates (chybí <ORDERS>).');
        }

        $reader = new XMLReader;

        if (! $reader->XML($xml, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new AutomationException('Export objednávek nejde přečíst.');
        }

        $orders = [];
        $previous = libxml_use_internal_errors(true);

        try {
            // Po objednávce skočí next() rovnou za ni (i na další <ORDER> bez mezery),
            // proto se read() volá jen mimo objednávky.
            $more = $reader->read();

            while ($more) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->name !== 'ORDER') {
                    $more = $reader->read();

                    continue;
                }

                $order = $reader->expand();

                if ($order instanceof DOMElement) {
                    $header = self::child($order, 'HEADER');
                    $number = self::text($order, 'ORDER_NUMBER') ?? self::text($header, 'ORDER_NUMBER') ?? self::text($header, 'CODE') ?? self::text($order, 'CODE');
                    $status = self::text($order, 'STATUS') ?? self::text($header, 'STATUS');

                    if ($number !== null && $status !== null) {
                        $orders[$number] = [
                            'status' => $status,
                            'updated_at' => self::time(self::text($order, 'UPDATED_AT') ?? self::text($header, 'UPDATED_AT')),
                        ];

                        $boxes = self::boxes($order);

                        if ($boxes !== []) {
                            $orders[$number]['created_at'] = self::time(self::text($order, 'CREATED_AT') ?? self::text($header, 'CREATED_AT'));
                            $orders[$number]['boxes'] = $boxes;
                        }
                    }
                }

                $more = $reader->next();
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $reader->close();
        }

        return $orders;
    }

    /** @return list<array<string, mixed>> dárkové balíčky z poznámek položek */
    private static function boxes(DOMElement $order): array
    {
        $boxes = [];

        foreach (self::child($order, 'ITEMS')?->childNodes ?? [] as $item) {
            $note = $item instanceof DOMElement && $item->tagName === 'ITEM' ? self::text($item, 'NOTE') : null;
            $box = $note !== null ? GiftBoxes::fromNote((string) self::text($item, 'TITLE'), $note) : null;

            if ($box !== null) {
                $boxes[] = $box;
            }
        }

        return $boxes;
    }

    private static function child(?DOMElement $parent, string $name): ?DOMElement
    {
        foreach ($parent?->childNodes ?? [] as $node) {
            if ($node instanceof DOMElement && $node->tagName === $name) {
                return $node;
            }
        }

        return null;
    }

    private static function text(?DOMElement $parent, string $name): ?string
    {
        $value = trim((string) self::child($parent, $name)?->textContent);

        return $value === '' ? null : $value;
    }

    private static function time(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value, 'Europe/Prague')->toIso8601String();
        } catch (Throwable) {
            return null;
        }
    }
}
