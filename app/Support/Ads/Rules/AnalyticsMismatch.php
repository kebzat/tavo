<?php

namespace App\Support\Ads\Rules;

use App\Enums\Ads\AlertSeverity;
use App\Enums\Ads\PrimaryGoal;
use App\Support\Ads\AnalyticsStats;
use App\Support\Ads\Format;

/**
 * Reklamy hlásí nákupy, které web (GA4) vůbec nenaměřil, nebo si jich
 * připisují mnohem víc, než kolik web naměřil ze všech zdrojů dohromady.
 * Pomáhá odlišit výpadek měření od skutečného propadu obchodu.
 *
 * Čísla se nikdy nesčítají: reklamní systémy si nákupy připisují samy
 * (často dvakrát), GA4 je jeden pohled webu. Viz docs/ADS.md.
 */
class AnalyticsMismatch implements Rule
{
    private const MIN_AD_PURCHASES = 3;

    private const RATIO = 1.5;

    public function key(): string
    {
        return 'analytics_mismatch';
    }

    public function evaluate(AlertContext $context): array
    {
        $analyticsIds = $context->client->activeAnalyticsAccountIds();

        if ($context->goal() !== PrimaryGoal::Purchases || $analyticsIds === []) {
            return [];
        }

        $week = $context->week();
        $adPurchases = $context->totals($week)->get('purchases');
        $web = AnalyticsStats::totals($analyticsIds, $week);

        if ($adPurchases < self::MIN_AD_PURCHASES || $web['sessions'] <= 0) {
            return [];
        }

        $numbers = 'Za posledních 7 dní si reklamy připsaly '.Format::count($adPurchases).' nákupů, GA4 naměřila na webu ze všech zdrojů '.Format::count($web['purchases']).'.';

        if ($web['purchases'] <= 0) {
            return [new Finding(
                AlertSeverity::Critical,
                'Měření: web nehlásí žádné nákupy',
                'Nejspíš nefunguje měření nákupu v GA4 (událost purchase), ne obchod. Ověřte v GA4 → Přehledy → Zpeněžení a v e-shopu počet objednávek za stejné dny.',
                ['ad_purchases' => $adPurchases, 'web_purchases' => 0, 'sessions' => $web['sessions']],
                facts: $numbers.' Web má návštěvy ('.Format::number($web['sessions']).'), ale žádný nákup.',
            )];
        }

        if ($adPurchases <= $web['purchases'] * self::RATIO) {
            return [];
        }

        return [new Finding(
            AlertSeverity::Info,
            'Reklamy si připisují víc nákupů, než web naměřil',
            'Část rozdílu je normální (atribuce po zhlédnutí, různá okna). Takový rozdíl ale ukazuje na zdvojené měření nebo příliš štědrou atribuci. Při hodnocení výkonu se opřete o objednávky z e-shopu, ne o součet z reklam.',
            ['ad_purchases' => $adPurchases, 'web_purchases' => $web['purchases']],
            facts: $numbers,
        )];
    }
}
