<?php

namespace App\Support\Crm\Scout;

use App\Enums\Crm\CompanySegment;
use App\Enums\Crm\FitVerdict;

/**
 * Skóre 0–100: jak firma sedí na to, co Taveo prodává.
 *
 * Kritéria vycházejí z docs/BRAND-STRATEGY.md (kapitoly 4.1 a 4.3):
 * menší a střední e-shopy a firmy s rozběhnutým webem a marketingem,
 * na platformě, kterou Tom umí, s rozpočtem a s něčím, co jde opravit.
 * Malý web bez známek rozpočtu nebo uzavřený systém se nehodí, i kdyby
 * měl chyb nejvíc.
 *
 * Hledáme jen e-shopy. Firma z jiného segmentu nebo web bez e-shopu
 * dostane verdikt „Nehodí se" bez ohledu na skóre.
 *
 * Skóre je jen z měření. Úsudek Clauda ho umí posunout o ±20 bodů
 * (viz ProspectScout), ne přepsat.
 */
class FitScorer
{
    /** Platformy, na kterých Tom dodá úpravy bez čekání na cizí tým. */
    private const OUR_PLATFORMS = ['Shoptet', 'Upgates', 'Shopify', 'WooCommerce', 'WordPress', 'PrestaShop'];

    /** Segmenty, ve kterých může být e-shop. Ostatní (služby, agentury…) se odkládají. */
    public const ESHOP_SEGMENTS = [CompanySegment::Eshop, CompanySegment::FormerClient, CompanySegment::Other];

    /** Stavebnice a uzavřené systémy, kde jde upravit jen málo. */
    private const CLOSED_PLATFORMS = ['Webnode', 'Wix', 'Squarespace', 'Webareal', 'Eshop-rychle', 'ByznysWeb'];

    /**
     * @param  array<string, mixed>  $m  měření z WebScout
     * @param  list<array<string, string>>  $findings
     * @return array{score: int, verdict: FitVerdict, reasons: list<string>}
     */
    public static function score(array $m, array $findings, ?CompanySegment $segment = null): array
    {
        if (! ($m['reachable'] ?? false)) {
            return [
                'score' => 0,
                'verdict' => FitVerdict::Unreachable,
                'reasons' => [($m['error'] ?? null) ?: 'Web nejde načíst.'],
            ];
        }

        $score = 0;
        $reasons = [];
        $add = function (int $points, string $reason) use (&$score, &$reasons): void {
            $score += $points;
            $reasons[] = ($points > 0 ? '+' : '').$points.' '.$reason;
        };

        $platform = $m['platform'] ?? null;
        $tracking = $m['tracking'] ?? [];
        $urls = $m['sitemap_urls'] ?? null;

        // Co firma je
        if ($m['is_eshop'] ?? false) {
            $add(25, 'e-shop');
        } else {
            $add(5, 'web služeb, ne e-shop');
        }

        match (true) {
            in_array($platform, self::OUR_PLATFORMS, true) => $add(15, "platforma {$platform}, na ní umíme dodat"),
            in_array($platform, self::CLOSED_PLATFORMS, true) => $add(-10, "stavebnice {$platform}, úpravy jsou omezené"),
            default => $add(5, $platform ? "platforma {$platform}" : 'platformu jsme nepoznali'),
        };

        // Rozpočet: kdo platí reklamu, má peníze na marketing i web
        if ($tracking['meta_pixel'] ?? false) {
            $add(10, 'Meta pixel, nejspíš platí reklamu na sítích');
        }
        if (($tracking['google_ads'] ?? false) || ($tracking['sklik'] ?? false)) {
            $add(10, 'Google Ads nebo Sklik, platí reklamu ve vyhledávání');
        }
        if (($tracking['ga4'] ?? false) || ($tracking['gtm'] ?? false)) {
            $add(5, 'měří návštěvnost');
        }
        if (($tracking['heureka'] ?? false) || ($tracking['zbozi'] ?? false)) {
            $add(5, 'je na Heurece nebo Zboží.cz');
        }

        // Velikost
        if ($urls !== null) {
            match (true) {
                $urls >= 100 => $add(10, "{$urls} adres v sitemapě, rozsáhlý katalog"),
                $urls >= 30 => $add(5, "{$urls} adres v sitemapě"),
                $urls < 10 && ! ($m['is_eshop'] ?? false) => $add(-10, "jen {$urls} adres, malý web"),
                default => null,
            };
        }

        // Život webu
        $year = $m['copyright_year'] ?? null;
        $now = (int) now()->format('Y');

        if ($year !== null && $year >= $now - 1) {
            $add(5, 'web se udržuje');
        } elseif ($year !== null && $year <= $now - 5) {
            $add(-5, "patička z roku {$year}, web nejspíš nikdo nerozvíjí");
        }

        // Je s čím přijít
        $serious = count(array_filter($findings, fn (array $f): bool => in_array($f['severity'], ['critical', 'high'], true)));

        match (true) {
            $serious >= 2 => $add(10, "{$serious} závažné nálezy, je s čím přijít"),
            $serious === 1 => $add(5, 'jeden závažný nález'),
            default => $add(0, 'žádný závažný nález, těžko se oslovuje'),
        };

        $score = max(0, min(100, $score));
        $verdict = FitVerdict::fromScore($score);

        // Hledáme jen e-shopy (rozhodnutí Toma z 27. 9. 2026). Firma z jiného
        // segmentu nebo web, který e-shop není, se do fronty nedostane, ať má
        // skóre jakékoli. Skóre zůstává, kdyby se rozhodnutí změnilo.
        $notEshop = match (true) {
            $segment !== null && ! in_array($segment, self::ESHOP_SEGMENTS, true) => 'segment „'.$segment->getLabel().'", hledáme jen e-shopy',
            ! ($m['is_eshop'] ?? false) => 'web nevypadá jako e-shop, hledáme jen e-shopy',
            default => null,
        };

        if ($notEshop !== null) {
            $reasons[] = $notEshop;
            $verdict = FitVerdict::Poor;
        }

        return ['score' => $score, 'verdict' => $verdict, 'reasons' => $reasons];
    }
}
