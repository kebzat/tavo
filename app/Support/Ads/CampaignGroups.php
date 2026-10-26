<?php

namespace App\Support\Ads;

use App\Models\Ads\AdCampaign;
use App\Models\Ads\AdCampaignGroup;
use App\Models\Client;
use Illuminate\Support\Collection;

/**
 * Zařazování kampaní do skupin. Kampaň patří nejvýš do jedné skupiny, ať se
 * útrata nikde nepočítá dvakrát. Ruční zařazení (i „nezařazovat“) má přednost
 * před pravidlem z názvu.
 */
final class CampaignGroups
{
    /** Za kolik dní zpátky se nezařazená kampaň s útratou hlásí jako „čeká na zařazení“. */
    public const WAITING_DAYS = 30;

    /** @return Collection<int, AdCampaign> Všechny kampaně klienta (zapnuté reklamní účty). */
    public static function campaigns(Client $client): Collection
    {
        return AdCampaign::query()
            ->whereIn('ad_account_id', $client->activeAdAccountIds())
            ->with(['account', 'group'])
            ->get();
    }

    /**
     * Kampaně do zaškrtávání: název, účet, stav a útrata za 30 dní, od největší.
     *
     * @return array<int, string> id => popisek
     */
    public static function options(Client $client): array
    {
        $spend = Stats::byCampaign($client->activeAdAccountIds(), self::recent());
        $multi = count($client->activeAdAccountIds()) > 1;

        return self::campaigns($client)
            ->sortByDesc(fn (AdCampaign $campaign): float => ($spend[$campaign->getKey()] ?? Metrics::empty())->spend())
            ->mapWithKeys(fn (AdCampaign $campaign): array => [$campaign->getKey() => collect([
                $campaign->name,
                $multi ? $campaign->account?->name : null,
                $campaign->statusLabel(),
                '30 dní '.Format::money(($spend[$campaign->getKey()] ?? Metrics::empty())->spend(), $client->adCurrency()),
                $campaign->group ? 've skupině '.$campaign->group->name : null,
            ])->filter()->implode(' · ')])
            ->all();
    }

    /**
     * Uloží, které kampaně patří do skupiny. Zaškrtnuté se přesunou sem
     * (i z jiné skupiny), odškrtnuté zůstanou nezařazené a pravidlo z názvu
     * je zpátky nepřidá.
     *
     * @param  list<int|string>  $campaignIds
     */
    public static function sync(AdCampaignGroup $group, array $campaignIds): void
    {
        $ids = array_map('intval', $campaignIds);
        $allowed = self::campaigns($group->client)->pluck('id')->all();
        $ids = array_values(array_intersect($ids, $allowed));

        AdCampaign::query()
            ->where('ad_campaign_group_id', $group->getKey())
            ->whereNotIn('id', $ids ?: [0])
            ->update(['ad_campaign_group_id' => null, 'grouped_by' => 'manual']);

        AdCampaign::query()->whereIn('id', $ids)->update(['ad_campaign_group_id' => $group->getKey(), 'grouped_by' => 'manual']);
    }

    /** Ruční přesun jedné kampaně, null = nezařazená. */
    public static function assign(Client $client, int $campaignId, ?int $groupId): void
    {
        $campaign = self::campaigns($client)->firstWhere('id', $campaignId);
        $group = $groupId ? $client->adCampaignGroups()->whereKey($groupId)->first() : null;

        $campaign?->update(['ad_campaign_group_id' => $group?->getKey(), 'grouped_by' => 'manual']);
    }

    /**
     * Pravidlo z názvu na nezařazené kampaně, kterých se ještě nikdo nedotkl.
     * Volá se po uložení skupiny, ranní stažení to dělá u nových kampaní samo.
     *
     * @return int kolik kampaní se zařadilo
     */
    public static function applyRules(Client $client): int
    {
        $groups = $client->adCampaignGroups()->get()->filter(fn (AdCampaignGroup $group): bool => $group->ruleWords() !== []);
        $count = 0;

        if ($groups->isEmpty()) {
            return 0;
        }

        foreach (self::campaigns($client)->whereNull('ad_campaign_group_id')->whereNull('grouped_by') as $campaign) {
            $match = $groups->first(fn (AdCampaignGroup $group): bool => $group->matchesName($campaign->name));

            if ($match) {
                $campaign->update(['ad_campaign_group_id' => $match->getKey(), 'grouped_by' => 'rule']);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Nové kampaně čekající na zařazení: nezařazené, nikdo je ručně neodložil
     * mimo skupiny a za posledních 30 dní utrácely. U klienta bez skupin
     * prázdné, tam se nic nezařazuje.
     *
     * @return Collection<int, AdCampaign>
     */
    public static function waiting(Client $client): Collection
    {
        if (! $client->adCampaignGroups()->exists()) {
            return collect();
        }

        $spend = Stats::byCampaign($client->activeAdAccountIds(), self::recent());

        return self::campaigns($client)
            ->whereNull('ad_campaign_group_id')
            ->whereNull('grouped_by')
            ->filter(fn (AdCampaign $campaign): bool => ($spend[$campaign->getKey()] ?? Metrics::empty())->spend() > 0)
            ->values();
    }

    private static function recent(): Period
    {
        return Period::between(now()->subDays(self::WAITING_DAYS), now()->subDay());
    }
}
