<?php

namespace App\Support\Ads;

use App\Enums\Ads\PrimaryGoal;
use App\Models\Ads\AdBusinessResult;
use App\Models\Ads\AdCampaignGroup;
use App\Models\Client;
use App\Models\Marketing\LeadFeedback;

/**
 * Obchodní výsledky vedle čísel z reklam.
 *
 * E-shop: objednávky a tržby z e-shopu proti útratě a proti tomu, co si
 * připsaly reklamní systémy. Konverze z Mety a Googlu se nesčítají jako
 * tržby, jedna objednávka se může připsat oběma.
 *
 * Leady: co se s leady stalo u klienta (ozvali se, relevantní, obchody),
 * přepočtené na útratu za stejné období. Levný formulář není úspěch, když
 * z něj nic není.
 *
 * U klienta se skupinami se útrata bere ze skupiny s odpovídajícím cílem
 * (e-shop ze skupiny Prodej, leady ze skupiny Nábor), jinak celý účet.
 */
final class BusinessResults
{
    /**
     * Útrata, se kterou se výsledky porovnávají: skupina s daným cílem,
     * jinak celý účet. Null, když klient ani žádná skupina takový cíl nemá.
     *
     * @param  list<PrimaryGoal>  $goals
     */
    public static function scopeFor(Client $client, array $goals): ?AdsScope
    {
        $group = $client->adCampaignGroups()->get()->first(fn (AdCampaignGroup $group): bool => in_array($group->goal, $goals, true));

        return match (true) {
            $group !== null => new AdsScope($client, $group),
            in_array($client->adGoal(), $goals, true) => new AdsScope($client),
            default => null,
        };
    }

    /**
     * @return list<array{period: string, source: ?string, orders: string, revenue: string, spend: string, cos: string, mer: string, attributed: string, note: ?string}>
     */
    public static function shop(Client $client, ?AdsScope $scope = null, int $limit = 6): array
    {
        $scope ??= new AdsScope($client);
        $ids = $client->activeAdAccountIds();
        $currency = $client->adCurrency();

        return $client->adBusinessResults()->limit($limit)->get()->map(function (AdBusinessResult $row) use ($ids, $currency, $scope): array {
            $ads = Stats::totals($ids, Period::between($row->period_from, $row->period_to), $scope->campaignIds());
            $spend = $ads->spend();
            $revenue = (float) $row->revenue;

            return [
                'period' => Period::between($row->period_from, $row->period_to)->label(),
                'source' => $row->source,
                'orders' => $row->orders === null ? '–' : Format::count($row->orders),
                'revenue' => $row->revenue === null ? '–' : Format::money($revenue, $currency),
                'spend' => Format::money($spend, $currency),
                // Náklady na obrat (PNO, COS): kolik procent tržeb stály reklamy.
                'cos' => $revenue > 0 ? Format::percent($spend / $revenue * 100, 1) : '–',
                // Tržby na korunu reklamy (MER), ze skutečných tržeb, ne z atribuce.
                'mer' => $spend > 0 && $revenue > 0 ? Format::roas($revenue / $spend) : '–',
                'attributed' => $revenue > 0
                    ? Format::count($ads->get('purchases')).' nákupů za '.Format::money($ads->get('purchase_value'), $currency).' ('.Format::percent(min(999, $ads->get('purchase_value') / $revenue * 100), 0).' tržeb)'
                    : Format::count($ads->get('purchases')).' nákupů',
                'note' => $row->note,
            ];
        })->all();
    }

    /**
     * @return list<array{period: string, source: ?string, leads: string, ad_leads: string, qualified: string, deals: string, deal_value: string, cost_lead: string, cost_qualified: string, cost_deal: string, note: ?string}>
     */
    public static function leads(Client $client, ?AdsScope $scope = null, int $limit = 6): array
    {
        $scope ??= new AdsScope($client);
        $ids = $client->activeAdAccountIds();
        $currency = $client->adCurrency();

        return $client->leadFeedback()->limit($limit)->get()->map(function (LeadFeedback $row) use ($ids, $currency, $scope): array {
            $ads = Stats::totals($ids, Period::between($row->period_from, $row->period_to), $scope->campaignIds());
            $spend = $ads->spend();
            $per = fn (?int $count): string => $count ? Format::unitPrice($spend / $count, $currency) : '–';

            return [
                'period' => Period::between($row->period_from, $row->period_to)->label(),
                'source' => $row->source,
                'leads' => $row->leads === null ? '–' : Format::count($row->leads),
                'ad_leads' => Format::count($ads->get('leads')),
                'qualified' => $row->qualified === null ? '–' : Format::count($row->qualified).($row->leads ? ' ('.Format::percent($row->qualified / $row->leads * 100, 0).')' : ''),
                'deals' => $row->deals === null ? '–' : Format::count($row->deals),
                'deal_value' => $row->deal_value === null ? '–' : Format::money((float) $row->deal_value, $currency),
                'cost_lead' => $per($row->leads),
                'cost_qualified' => $per($row->qualified),
                'cost_deal' => $per($row->deals),
                'note' => $row->note,
            ];
        })->all();
    }
}
