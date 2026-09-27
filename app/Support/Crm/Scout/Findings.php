<?php

namespace App\Support\Crm\Scout;

use Illuminate\Support\Number;

/**
 * Z měření webu udělá nálezy pro audit a checklist.
 *
 * Každý nález vychází z konkrétního změřeného čísla nebo značky v kódu.
 * Tvrzení o tržbách, konverzích nebo pozicích ve vyhledávání tu nejsou,
 * protože zvenku změřit nejdou. Texty čte klient, platí pro ně pravidla
 * z .claude/skills/tavo-copy.
 *
 * Tvar nálezu:
 *   key       strojový klíč (testy, deduplikace v checklistu)
 *   area      oblast, podle ní se audit i checklist dělí na kapitoly
 *   severity  critical | high | medium | low
 *   title     nadpis nálezu
 *   detail    co jsme zjistili a proč na tom záleží
 *   fix       co s tím udělat, zároveň položka checklistu
 *   task      krátký název úkolu do checklistu
 */
class Findings
{
    public const AREAS = [
        'security' => 'Zabezpečení',
        'mobile' => 'Mobil a rychlost',
        'search' => 'Vyhledávače',
        'ai' => 'AI vyhledávání',
        'measurement' => 'Měření',
        'trust' => 'Důvěra a sdílení',
    ];

    public const SEVERITY_TAGS = [
        'critical' => 'kritické',
        'high' => 'vysoké',
        'medium' => 'střední',
        'low' => 'nízké',
    ];

    private const SEVERITY_ORDER = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];

    /**
     * @param  array<string, mixed>  $m  výsledek WebScout::measure()
     * @return list<array<string, string>> seřazené od nejzávažnějšího
     */
    public static function from(array $m): array
    {
        if (! ($m['reachable'] ?? false)) {
            return [];
        }

        $domain = parse_url((string) ($m['final_url'] ?? ''), PHP_URL_HOST) ?: 'web';
        $f = [];

        // Zabezpečení
        if (! ($m['ssl_valid'] ?? true)) {
            $f[] = self::make('ssl_invalid', 'security', 'critical',
                'Prohlížeč varuje před neplatným certifikátem',
                "Při otevření {$domain} přes HTTPS hlásí prohlížeč chybu certifikátu a místo webu ukáže varování. Většina lidí v tu chvíli odejde.",
                'Obnovit nebo správně nastavit SSL certifikát u hostingu. Certifikát Let\'s Encrypt je u většiny hostingů zdarma.',
                'Opravit SSL certifikát');
        } elseif (! ($m['https'] ?? false)) {
            $f[] = self::make('no_https', 'security', 'critical',
                'Web neběží na zabezpečené adrese',
                'Adresa zůstává na http://. Chrome i Safari takovou stránku v adresním řádku označí jako nezabezpečenou.',
                'Zapnout certifikát a všechny adresy trvale přesměrovat na https://.',
                'Přejít na HTTPS');
        } elseif (($m['https_redirect'] ?? null) === false) {
            $f[] = self::make('no_https_redirect', 'security', 'high',
                'Nezabezpečená verze webu nepřesměrovává',
                "Na http://{$domain} se web načte bez přesměrování na HTTPS. Vyhledávač pak vidí dvě kopie téhož webu.",
                'Nastavit trvalé přesměrování (301) z http:// na https://.',
                'Přesměrovat http na https');
        }

        // Mobil a rychlost
        if (! ($m['viewport'] ?? true)) {
            $f[] = self::make('no_viewport', 'mobile', 'critical',
                'Stránka není nastavená pro mobil',
                'V kódu chybí značka viewport. Telefon pak vykreslí zmenšenou verzi pro počítač a text je potřeba přibližovat prsty.',
                'Doplnit viewport a projít šablonu na šířce telefonu. Když šablona responzivní není vůbec, jde o větší zásah.',
                'Zprovoznit web na mobilu');
        }

        if ($ps = $m['pagespeed'] ?? null) {
            $lcp = Number::format($ps['lcp_ms'] / 1000, 1, locale: 'cs');

            if ($ps['score'] < 50) {
                $f[] = self::make('pagespeed_low', 'mobile', 'high',
                    'Na mobilu se web načítá pomalu',
                    "Google PageSpeed dává webu na mobilu {$ps['score']} ze 100 bodů. Hlavní obsah se ukáže za {$lcp} s, Google doporučuje do 2,5 s.",
                    'Zmenšit obrázky a převést je do WebP, odložit skripty třetích stran a zjistit, co brzdí první načtení.',
                    'Zrychlit načítání na mobilu');
            } elseif ($ps['score'] < 90) {
                $f[] = self::make('pagespeed_mid', 'mobile', 'medium',
                    'Rychlost na mobilu má rezervy',
                    "Google PageSpeed dává webu na mobilu {$ps['score']} ze 100 bodů, hlavní obsah se ukáže za {$lcp} s.",
                    'Projít obrázky a skripty, které se načítají hned na začátku, a odložit ty, které počkají.',
                    'Doladit rychlost na mobilu');
            }
        }

        if (($m['response_ms'] ?? 0) > 1500) {
            $seconds = Number::format($m['response_ms'] / 1000, 1, locale: 'cs');
            $f[] = self::make('slow_server', 'mobile', 'medium',
                'Server odpovídá pomalu',
                "Úvodní stránka přišla ze serveru za {$seconds} s. Stejně dlouho se čeká na každou další stránku.",
                'Prověřit hosting a cache. U pronajatých platforem jako Shoptet nebo Upgates to jde přes podporu.',
                'Zrychlit odpověď serveru');
        }

        // Vyhledávače
        $title = (string) ($m['title'] ?? '');

        if ($title === '') {
            $f[] = self::make('no_title', 'search', 'high',
                'Úvodní stránka nemá titulek',
                'Titulek je první řádek výsledku v Googlu. Bez něj si ho Google poskládá sám.',
                'Napsat titulek do 60 znaků: co nabízíte a značka.',
                'Doplnit titulek úvodní stránky');
        } elseif (mb_strlen($title) > 65) {
            $f[] = self::make('long_title', 'search', 'low',
                'Titulek je delší, než Google zobrazí',
                'Titulek „'.$title.'" má '.mb_strlen($title).' znaků. Google ukáže zhruba 60, zbytek usekne.',
                'Zkrátit titulek pod 60 znaků a to důležité dát na začátek.',
                'Zkrátit titulek úvodní stránky');
        }

        if (($m['meta_description'] ?? null) === null) {
            $f[] = self::make('no_description', 'search', 'medium',
                'Chybí popisek pro vyhledávače',
                'Google si pak do výsledků vybere kus textu ze stránky sám. Často je to menu nebo text cookie lišty.',
                'Napsat popisek o 140 až 160 znacích, ze kterého je jasné, proč kliknout.',
                'Napsat popisek úvodní stránky');
        }

        $h1 = (int) ($m['h1_count'] ?? 1);

        if ($h1 === 0) {
            $f[] = self::make('no_h1', 'search', 'medium',
                'Úvodní stránka nemá hlavní nadpis',
                'Hlavní nadpis (H1) říká vyhledávači, o čem stránka je. Na úvodní stránce žádný není.',
                'Doplnit jeden nadpis H1, který pojmenuje, co firma dělá.',
                'Doplnit hlavní nadpis');
        } elseif ($h1 > 1) {
            $f[] = self::make('many_h1', 'search', 'low',
                "Na úvodní stránce je {$h1} hlavních nadpisů",
                'Hlavní nadpis má být na stránce jeden. Víc nadpisů H1 bývá pozůstatek šablony.',
                'Nechat jeden H1, ostatní změnit na nižší úroveň.',
                'Sjednotit hlavní nadpisy');
        }

        if (! ($m['sitemap_found'] ?? true)) {
            $f[] = self::make('no_sitemap', 'search', 'medium',
                'Web nemá mapu stránek',
                'Na obvyklých adresách ani v robots.txt jsme sitemapu nenašli. Podle ní vyhledávače zjišťují, které stránky existují.',
                'Vygenerovat sitemap.xml, uvést ji v robots.txt a odeslat do Google Search Console.',
                'Vytvořit sitemapu');
        }

        if (! ($m['canonical'] ?? true)) {
            $f[] = self::make('no_canonical', 'search', 'low',
                'Chybí kanonická adresa',
                'Když se stejná stránka otevře pod víc adresami (s parametry, s lomítkem a bez), kanonická adresa říká, která je ta pravá.',
                'Doplnit do šablony odkaz rel="canonical".',
                'Doplnit kanonické adresy');
        }

        $types = $m['schema_types'] ?? [];
        $knowsCompany = array_intersect($types, ['Organization', 'LocalBusiness', 'Store', 'OnlineStore', 'ProfessionalService', 'Corporation']) !== [];

        if (! $knowsCompany) {
            $f[] = self::make('no_org_schema', 'search', 'medium',
                'Chybí strukturovaná data o firmě',
                'V kódu nejsou údaje o firmě ve formátu schema.org: název, adresa, kontakt. Z nich skládá Google panel s firmou a čerpají z nich i AI asistenti.',
                'Doplnit do šablony Organization nebo LocalBusiness s adresou a kontaktem.',
                'Doplnit strukturovaná data o firmě');
        }

        // AI vyhledávání
        $blocked = self::blockedAiBots($m);

        if ($blocked !== []) {
            $f[] = self::make('ai_blocked', 'ai', 'high',
                'AI asistenti se na web nedostanou',
                'Zkusili jsme web otevřít jako roboti AI asistentů: '
                    .collect($blocked)->map(fn (string $why, string $bot): string => "{$bot} ({$why})")->join(', ')
                    .'. '.self::assistantsOf(array_keys($blocked)).' pak obsah webu '
                    .(count($blocked) > 1 ? 'nepřečtou a při odpovědích čerpají' : 'nepřečte a při odpovědích čerpá').' odjinud.',
                'Povolit AI roboty v robots.txt. Když je blokuje hosting nebo platforma, požádat o výjimku podporu.',
                'Pustit na web AI roboty');
        }

        // Měření
        $tracking = $m['tracking'] ?? [];

        if (! ($tracking['ga4'] ?? false) && ! ($tracking['gtm'] ?? false)) {
            $f[] = self::make('no_analytics', 'measurement', 'high',
                'V kódu webu jsme nenašli měření návštěvnosti',
                'Bez Google Analytics nebo podobného nástroje nejde říct, odkud lidé chodí a kde odcházejí. Měřicí kód se někdy načítá až po souhlasu s cookies, proto to ověříme s vámi.',
                'Nasadit GA4 přes Google Tag Manager se souhlasem s cookies (Consent Mode v2).',
                'Nasadit měření návštěvnosti');
        }

        // Důvěra a sdílení
        $year = $m['copyright_year'] ?? null;

        if ($year !== null && $year <= (int) now()->format('Y') - 3) {
            $f[] = self::make('old_copyright', 'trust', 'low',
                "Patička hlásí rok {$year}",
                'Návštěvník podle toho soudí, jestli firma ještě funguje a jestli jsou ceny a kontakty aktuální.',
                'Rok v patičce generovat automaticky.',
                'Aktualizovat rok v patičce');
        }

        if (! ($m['og_image'] ?? true)) {
            $f[] = self::make('no_og_image', 'trust', 'low',
                'Odkaz na web nemá náhledový obrázek',
                'Při sdílení na Facebooku nebo v Messengeru se ukáže holý odkaz, nebo náhodný obrázek ze stránky.',
                'Doplnit náhledový obrázek (og:image) 1200 × 630 px.',
                'Doplnit náhledový obrázek pro sdílení');
        }

        usort($f, fn (array $a, array $b): int => self::SEVERITY_ORDER[$a['severity']] <=> self::SEVERITY_ORDER[$b['severity']]);

        return $f;
    }

    /**
     * Co je v pořádku. Patří do auditu stejně jako nálezy: klient vidí,
     * že jsme se dívali pořádně, a ne jen hledali chyby.
     *
     * @param  array<string, mixed>  $m
     * @return list<array{area: string, title: string}>
     */
    public static function passed(array $m): array
    {
        if (! ($m['reachable'] ?? false)) {
            return [];
        }

        $passed = [];
        $ok = function (string $area, string $title) use (&$passed): void {
            $passed[] = ['area' => $area, 'title' => $title];
        };

        if (($m['ssl_valid'] ?? false) && ($m['https'] ?? false) && ($m['https_redirect'] ?? null) !== false) {
            $ok('security', 'HTTPS s platným certifikátem');
        }
        if ($m['viewport'] ?? false) {
            $ok('mobile', 'Web je nastavený pro mobil');
        }
        if (($m['pagespeed']['score'] ?? 0) >= 90) {
            $ok('mobile', 'Rychlost na mobilu '.$m['pagespeed']['score'].' ze 100');
        }
        if (($m['title'] ?? null) && ($m['meta_description'] ?? null)) {
            $ok('search', 'Titulek a popisek úvodní stránky');
        }
        if ($m['sitemap_found'] ?? false) {
            $ok('search', 'Sitemapa je dostupná');
        }
        if (self::blockedAiBots($m) === []) {
            $ok('ai', 'AI roboti se na web dostanou');
        }
        if (($m['tracking']['ga4'] ?? false) || ($m['tracking']['gtm'] ?? false)) {
            $ok('measurement', 'Měřicí kód je na webu');
        }

        return $passed;
    }

    /**
     * AI roboti bez přístupu a důvod. Zákaz v robots.txt i chybová odpověď
     * serveru, ať to způsobil kdokoli.
     *
     * @param  array<string, mixed>  $m
     * @return array<string, string> robot => důvod
     */
    public static function blockedAiBots(array $m): array
    {
        $blocked = [];

        foreach ($m['robots']['blocked_ai'] ?? [] as $bot) {
            $blocked[$bot] = 'zákaz v robots.txt';
        }

        foreach ($m['ai_bots'] ?? [] as $bot => $status) {
            if (! isset($blocked[$bot]) && ($status === 0 || $status >= 400)) {
                $blocked[$bot] = $status === 0 ? 'bez odpovědi' : "chyba {$status}";
            }
        }

        return $blocked;
    }

    /**
     * Asistenti, kterým roboti patří. „ChatGPT a Claude", ne jména robotů,
     * ta majitel webu nezná.
     *
     * @param  list<string>  $bots
     */
    private static function assistantsOf(array $bots): string
    {
        $names = array_map(fn (string $bot): string => [
            'GPTBot' => 'ChatGPT',
            'ClaudeBot' => 'Claude',
            'PerplexityBot' => 'Perplexity',
        ][$bot] ?? $bot, $bots);

        $last = array_pop($names);

        return $names === [] ? $last : implode(', ', $names).' ani '.$last;
    }

    public static function severityTag(string $severity): string
    {
        return self::SEVERITY_TAGS[$severity] ?? $severity;
    }

    /** @return array<string, string> */
    private static function make(string $key, string $area, string $severity, string $title, string $detail, string $fix, string $task): array
    {
        return compact('key', 'area', 'severity', 'title', 'detail', 'fix', 'task');
    }
}
