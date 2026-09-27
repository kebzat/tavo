<?php

namespace App\Support\Crm\Scout;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Změří veřejnou část webu. Jen to, co jde ověřit zvenku a doložit číslem:
 * odpověď serveru, HTTPS, značky v HTML, měřicí kódy, robots.txt, sitemapu,
 * přístup AI robotů a volitelně Google PageSpeed.
 *
 * Nic tu nehodnotí. Z výsledku skládá nálezy App\Support\Crm\Scout\Findings
 * a skóre App\Support\Crm\Scout\FitScorer. Audit, který z toho vznikne, jde
 * klientovi, takže každé tvrzení v něm musí stát na něčem, co se tu změřilo.
 */
class WebScout
{
    private const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36';

    /** Roboti, kterým se web ukazuje v AI vyhledávání. Klíč je jméno v robots.txt. */
    public const AI_BOTS = [
        'GPTBot' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)',
        'ClaudeBot' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)',
        'PerplexityBot' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; PerplexityBot/1.0; +https://perplexity.ai/perplexitybot)',
    ];

    /**
     * Otisky platforem v HTML. Pořadí je důležité: e-shopové platformy
     * běží často na WordPressu nebo mají v kódu obecné knihovny, takže
     * konkrétnější otisk musí vyhrát dřív.
     */
    private const PLATFORMS = [
        'Shoptet' => ['cdn.myshoptet.com', 'shoptet.cz', 'shoptet'],
        'Upgates' => ['upgates'],
        'Shopify' => ['cdn.shopify.com'],
        'WooCommerce' => ['woocommerce'],
        'PrestaShop' => ['prestashop'],
        'Magento' => ['mage/cookies', 'magento'],
        'FastCentrik' => ['fastcentrik'],
        'Eshop-rychle' => ['eshop-rychle'],
        'Webareal' => ['webareal'],
        'ByznysWeb' => ['byznysweb'],
        'Webnode' => ['webnode'],
        'Wix' => ['wixstatic.com', 'wix.com'],
        'Squarespace' => ['squarespace'],
        'WordPress' => ['wp-content', 'wp-includes'],
        'Joomla' => ['/media/jui/', 'joomla'],
        'Drupal' => ['drupal'],
        'Next.js' => ['__next_data__', '/_next/'],
    ];

    /** Platformy, na kterých běží e-shop. Samotný WordPress mezi ně nepatří. */
    public const ESHOP_PLATFORMS = ['Shoptet', 'Upgates', 'Shopify', 'WooCommerce', 'PrestaShop', 'Magento', 'FastCentrik', 'Eshop-rychle', 'Webareal', 'ByznysWeb'];

    /**
     * @return array<string, mixed>
     */
    public function measure(string $url): array
    {
        $result = [
            'input_url' => $url,
            'measured_at' => now()->toIso8601String(),
            'reachable' => false,
            'error' => null,
            'ssl_valid' => true,
        ];

        try {
            $response = $this->client()->get($url);
        } catch (ConnectionException $e) {
            // Neplatný certifikát shodí spojení, ale web za ním běží a má
            // smysl ho změřit. Zkusíme znovu bez ověření a zapíšeme si to.
            if (! $this->isCertificateError($e)) {
                return ['error' => $this->shortError($e)] + $result;
            }

            $result['ssl_valid'] = false;

            try {
                $response = $this->client()->withoutVerifying()->get($url);
            } catch (ConnectionException $e) {
                return ['error' => $this->shortError($e)] + $result;
            }
        }

        $finalUrl = (string) ($response->effectiveUri() ?? $url);
        $html = (string) $response->body();

        $result['status'] = $response->status();
        $result['final_url'] = $finalUrl;
        $result['reachable'] = $response->successful() && trim($html) !== '';
        $result['https'] = str_starts_with($finalUrl, 'https://');
        $result['response_ms'] = (int) round(($response->handlerStats()['total_time'] ?? 0) * 1000);
        $result['html_kb'] = (int) round(strlen($html) / 1024);

        if (! $result['reachable']) {
            return ['error' => $response->successful() ? 'Server vrátil prázdnou stránku' : 'Server vrátil chybu '.$response->status()] + $result;
        }

        $origin = $this->origin($finalUrl);
        $robots = $this->robots($origin, $result['ssl_valid']);

        return $result
            + $this->analyzeHtml($html)
            + ['https_redirect' => $result['https'] ? $this->redirectsToHttps($finalUrl) : false]
            + ['robots' => $robots]
            + ['ai_bots' => $this->aiBotStatuses($finalUrl, $result['ssl_valid'])]
            + ['pagespeed' => $this->pageSpeed($finalUrl)]
            + $this->sitemapFor($origin, $robots['sitemaps'], $result['ssl_valid']);
    }

    /*
    |--------------------------------------------------------------------------
    | Úvodní stránka
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, mixed>
     */
    private function analyzeHtml(string $html): array
    {
        $lower = Str::lower($html);
        $dom = $this->dom($html);
        $xpath = new \DOMXPath($dom);

        $meta = fn (string $query): ?string => ($node = $xpath->query($query)->item(0)) instanceof \DOMElement
            ? (trim($node->getAttribute('content')) ?: null)
            : null;

        $title = trim((string) $xpath->query('//title')->item(0)?->textContent);
        $schemaTypes = $this->schemaTypes($xpath);
        $platform = $this->platform($lower);

        return [
            'title' => $title !== '' ? Str::squish($title) : null,
            'meta_description' => $meta('//meta[translate(@name,"DESCRIPTION","description")="description"]'),
            'viewport' => $xpath->query('//meta[translate(@name,"VIEWPORT","viewport")="viewport"]')->length > 0,
            'canonical' => $xpath->query('//link[@rel="canonical"]')->length > 0,
            'og_image' => $meta('//meta[@property="og:image"]') !== null,
            'lang' => ($root = $xpath->query('//html')->item(0)) instanceof \DOMElement ? ($root->getAttribute('lang') ?: null) : null,
            'h1_count' => $xpath->query('//h1')->length,
            'schema_types' => $schemaTypes,
            'platform' => $platform,
            'is_eshop' => in_array($platform, self::ESHOP_PLATFORMS, true)
                || array_intersect($schemaTypes, ['Product', 'Offer', 'OfferCatalog']) !== []
                || Str::contains($lower, ['/kosik', 'do košíku', 'add-to-cart', 'addtocart', '/cart']),
            'copyright_year' => $this->copyrightYear($dom),
            'tracking' => [
                'ga4' => (bool) preg_match('/googletagmanager\.com\/gtag\/js|gtag\(\s*[\'"]config[\'"]\s*,\s*[\'"]G-/i', $html),
                'gtm' => (bool) preg_match('/googletagmanager\.com\/gtm\.js|GTM-[A-Z0-9]{4,}/', $html),
                'meta_pixel' => Str::contains($lower, ['connect.facebook.net', 'fbq(']),
                'google_ads' => (bool) preg_match('/AW-\d{6,}|googleadservices\.com/', $html),
                'sklik' => Str::contains($lower, ['c.seznam.cz/js/rc.js', 'imedia.cz', 'seznam_retargeting']),
                'heureka' => Str::contains($lower, ['heureka.cz/direct', 'im9.cz']),
                'zbozi' => Str::contains($lower, ['zbozi.cz']),
            ],
            'emails' => $this->emails($xpath, $html),
            'phones' => $this->phones($xpath),
            'text_excerpt' => Str::limit($this->visibleText($dom), 2000, ''),
        ];
    }

    private function dom(string $html): \DOMDocument
    {
        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);

        // Bez deklarace kódování by DOMDocument četl UTF-8 jako Latin-1
        // a z češtiny by v titulku zbyly paznaky.
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $dom;
    }

    /**
     * Typy ze strukturovaných dat JSON-LD, včetně těch zanořených v @graph.
     *
     * @return list<string>
     */
    private function schemaTypes(\DOMXPath $xpath): array
    {
        $types = [];

        foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
            $data = json_decode(trim($script->textContent), true);

            if (! is_array($data)) {
                continue;
            }

            array_walk_recursive($data, function ($value, $key) use (&$types): void {
                if ($key === '@type') {
                    $types[] = (string) $value;
                }
            });
        }

        return array_values(array_unique($types));
    }

    private function platform(string $lowerHtml): ?string
    {
        foreach (self::PLATFORMS as $name => $needles) {
            if (Str::contains($lowerHtml, $needles)) {
                return $name;
            }
        }

        return null;
    }

    /** Rok z „© 2019" v patičce. Rozsah „2015–2024" bere poslední rok. */
    private function copyrightYear(\DOMDocument $dom): ?int
    {
        $text = $this->visibleText($dom);

        if (! preg_match_all('/(?:©|&copy;|copyright)\s*(?:\d{4}\s*[-–]\s*)?((?:19|20)\d{2})/iu', $text, $m)) {
            return null;
        }

        $year = max(array_map('intval', $m[1]));

        return $year <= (int) now()->format('Y') ? $year : null;
    }

    private function visibleText(\DOMDocument $dom): string
    {
        $clone = clone $dom;

        foreach (['script', 'style', 'noscript', 'svg', 'template'] as $tag) {
            $nodes = iterator_to_array($clone->getElementsByTagName($tag));
            foreach ($nodes as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        $text = (string) $clone->getElementsByTagName('body')->item(0)?->textContent;

        // Web s rozbitým kódováním by jinak shodil regulární výrazy níž.
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        return (string) Str::squish($text);
    }

    /** @return list<string> */
    private function emails(\DOMXPath $xpath, string $html): array
    {
        $emails = [];

        foreach ($xpath->query('//a[starts-with(@href,"mailto:")]') as $link) {
            $emails[] = Str::of($link->getAttribute('href'))->after('mailto:')->before('?')->lower()->trim()->value();
        }

        preg_match_all('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', strip_tags($html), $m);

        return collect([...$emails, ...array_map('strtolower', $m[0])])
            ->filter(fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            // Obrázky typu logo@2x.png a adresy z knihoven do kontaktů nepatří.
            ->reject(fn (string $email): bool => (bool) preg_match('/\.(png|jpe?g|webp|svg|gif)$|sentry|example\.|wixpress/', $email))
            ->unique()
            ->take(3)
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function phones(\DOMXPath $xpath): array
    {
        $phones = [];

        foreach ($xpath->query('//a[starts-with(@href,"tel:")]') as $link) {
            $phones[] = preg_replace('/[^0-9+]/', '', Str::after($link->getAttribute('href'), 'tel:'));
        }

        return collect($phones)->filter(fn (string $phone): bool => strlen($phone) >= 9)->unique()->take(2)->values()->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Okolí úvodní stránky
    |--------------------------------------------------------------------------
    */

    /** Vede nezabezpečená adresa na HTTPS? Null, když se to nedá zjistit. */
    private function redirectsToHttps(string $httpsUrl): ?bool
    {
        $httpUrl = preg_replace('/^https:/', 'http:', $httpsUrl);

        try {
            $response = $this->client()->withOptions(['allow_redirects' => false])->timeout(8)->get($httpUrl);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->redirect()) {
            return false;
        }

        return str_starts_with((string) $response->header('Location'), 'https://');
    }

    /**
     * @return array{exists: bool, sitemaps: list<string>, blocked_ai: list<string>}
     */
    private function robots(string $origin, bool $verify): array
    {
        $body = $this->fetchText($origin.'/robots.txt', $verify);

        if ($body === null) {
            return ['exists' => false, 'sitemaps' => [], 'blocked_ai' => []];
        }

        preg_match_all('/^\s*sitemap:\s*(\S+)/im', $body, $m);

        return [
            'exists' => true,
            'sitemaps' => array_values(array_unique($m[1])),
            'blocked_ai' => $this->blockedAiBots($body),
        ];
    }

    /**
     * Roboti, kterým robots.txt zakazuje celý web. Bere jen skupinu
     * pojmenovanou přímo, zákaz pro `*` robots.txt často mívá pro jiné
     * účely a AI roboti ho čtou různě.
     *
     * @return list<string>
     */
    private function blockedAiBots(string $robots): array
    {
        $blocked = [];
        $agents = [];
        $inRules = false;

        foreach (preg_split('/\R/', $robots) as $line) {
            $line = trim(Str::before($line, '#'));

            if ($line === '') {
                continue;
            }

            [$field, $value] = array_map('trim', explode(':', $line, 2) + [1 => '']);
            $field = Str::lower($field);

            if ($field === 'user-agent') {
                // Nová skupina začíná, až když předchozí měla aspoň jedno pravidlo.
                if ($inRules) {
                    $agents = [];
                    $inRules = false;
                }
                $agents[] = Str::lower($value);

                continue;
            }

            $inRules = true;

            if ($field === 'disallow' && $value === '/') {
                foreach (array_keys(self::AI_BOTS) as $bot) {
                    if (in_array(Str::lower($bot), $agents, true)) {
                        $blocked[] = $bot;
                    }
                }
            }
        }

        return array_values(array_unique($blocked));
    }

    /**
     * Stavový kód, který dostane každý AI robot. Firewall nebo platforma je
     * občas blokuje, i když robots.txt nic nezakazuje.
     *
     * @return array<string, int>
     */
    private function aiBotStatuses(string $url, bool $verify): array
    {
        $statuses = [];

        foreach (self::AI_BOTS as $bot => $agent) {
            try {
                $request = $this->client()->withUserAgent($agent)->timeout(10);
                $statuses[$bot] = ($verify ? $request : $request->withoutVerifying())->get($url)->status();
            } catch (ConnectionException) {
                $statuses[$bot] = 0;
            }
        }

        return $statuses;
    }

    /**
     * Počet adres v sitemapě. U indexu sitemap sečte nejvýš pět dílčích,
     * víc by proklepnutí zbytečně zdrželo. Hranici pak přizná `sitemap_partial`.
     *
     * @param  list<string>  $declared  sitemapy uvedené v robots.txt
     * @return array{sitemap_found: bool, sitemap_urls: ?int, sitemap_partial: bool}
     */
    private function sitemapFor(string $origin, array $declared, bool $verify): array
    {
        $candidates = $declared ?: [$origin.'/sitemap.xml', $origin.'/sitemap_index.xml'];

        foreach ($candidates as $candidate) {
            $xml = $this->fetchText($candidate, $verify);

            if ($xml === null || ! Str::contains($xml, '<loc>')) {
                continue;
            }

            if (! Str::contains($xml, '<sitemapindex')) {
                return ['sitemap_found' => true, 'sitemap_urls' => substr_count($xml, '<loc>'), 'sitemap_partial' => false];
            }

            preg_match_all('/<loc>\s*([^<\s]+)\s*<\/loc>/', $xml, $m);
            $children = $m[1];
            $total = 0;

            foreach (array_slice($children, 0, 5) as $child) {
                $total += substr_count((string) $this->fetchText(html_entity_decode($child), $verify), '<loc>');
            }

            return ['sitemap_found' => true, 'sitemap_urls' => $total, 'sitemap_partial' => count($children) > 5];
        }

        return ['sitemap_found' => false, 'sitemap_urls' => null, 'sitemap_partial' => false];
    }

    /**
     * Google PageSpeed pro mobil. Volitelné: bez klíče má Google přísný
     * limit a měření trvá i půl minuty, takže když selže, audit se bez
     * něj obejde.
     *
     * @return array{score: int, lcp_ms: int, cls: float, tbt_ms: int}|null
     */
    private function pageSpeed(string $url): ?array
    {
        if (! config('services.pagespeed.enabled')) {
            return null;
        }

        try {
            $response = Http::timeout(90)->get('https://www.googleapis.com/pagespeedonline/v5/runPagespeed', array_filter([
                'url' => $url,
                'strategy' => 'mobile',
                'category' => 'performance',
                'key' => config('services.pagespeed.key'),
            ]));
        } catch (ConnectionException) {
            return null;
        }

        $lighthouse = $response->json('lighthouseResult');

        if (! $response->successful() || ! isset($lighthouse['categories']['performance']['score'])) {
            return null;
        }

        return [
            'score' => (int) round($lighthouse['categories']['performance']['score'] * 100),
            'lcp_ms' => (int) round($lighthouse['audits']['largest-contentful-paint']['numericValue'] ?? 0),
            'cls' => round((float) ($lighthouse['audits']['cumulative-layout-shift']['numericValue'] ?? 0), 3),
            'tbt_ms' => (int) round($lighthouse['audits']['total-blocking-time']['numericValue'] ?? 0),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Pomocné
    |--------------------------------------------------------------------------
    */

    private function client(): PendingRequest
    {
        return Http::withUserAgent(self::USER_AGENT)
            ->withHeaders(['Accept-Language' => 'cs,en;q=0.8'])
            ->timeout(15)
            ->connectTimeout(8);
    }

    private function fetchText(string $url, bool $verify): ?string
    {
        try {
            $request = $this->client()->timeout(10);
            $response = ($verify ? $request : $request->withoutVerifying())->get($url);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $body = (string) $response->body();

        // Velké e-shopy dávají dílčí sitemapy zabalené (sitemap.xml.gz).
        if (str_starts_with($body, "\x1f\x8b")) {
            $body = (string) @gzdecode($body);
        }

        return $body;
    }

    private function origin(string $url): string
    {
        $parts = parse_url($url);

        return ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');
    }

    private function isCertificateError(ConnectionException $e): bool
    {
        return Str::contains(Str::lower($e->getMessage()), ['ssl', 'certificate', 'curl error 60']);
    }

    private function shortError(ConnectionException $e): string
    {
        return match (true) {
            Str::contains($e->getMessage(), ['timed out', 'Timeout']) => 'Web neodpověděl do 15 s',
            Str::contains($e->getMessage(), ['resolve host', 'Could not resolve']) => 'Doména nemá DNS záznam',
            default => Str::limit($e->getMessage(), 120),
        };
    }
}
