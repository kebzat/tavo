<?php

namespace App\Console\Commands;

use App\Enums\Ads\ReportType;
use App\Models\Ads\AdCampaignGroup;
use App\Models\Client;
use App\Support\Ads\AdsScope;
use App\Support\Ads\Period;
use App\Support\Ads\ReportBuilder;
use Illuminate\Console\Command;

/**
 * Připraví koncepty reportů za minulý týden nebo měsíc. Nic neodesílá,
 * koncepty čekají v Reklamy → Reporty na komentář a odeslání.
 */
class AdsReports extends Command
{
    protected $signature = 'ads:reports {type=weekly : weekly nebo monthly}';

    protected $description = 'Založí koncepty týdenních nebo měsíčních reportů reklam';

    public function handle(ReportBuilder $builder): int
    {
        $type = ReportType::tryFrom($this->argument('type'));

        if (! in_array($type, [ReportType::Weekly, ReportType::Monthly], true)) {
            $this->error('Typ musí být weekly nebo monthly.');

            return self::INVALID;
        }

        $period = $type === ReportType::Weekly
            ? Period::week(now()->subWeek())
            : Period::month(now()->subMonthNoOverflow());

        $column = $type === ReportType::Weekly ? 'weekly_report' : 'monthly_report';

        $clients = Client::query()
            ->active()
            ->withAds()
            ->whereHas('adSettings', fn ($query) => $query->where($column, true))
            ->with(['adAccounts', 'adSettings'])
            ->get();

        $created = 0;

        foreach ($clients as $client) {
            if ($builder->exists($client, $type, $period)) {
                continue;
            }

            $builder->create($client, $type, $period);
            $created++;
            $this->info("{$client->name}: koncept {$period->label()}.");
        }

        // Skupiny kampaní se zapnutým reportem dostanou vlastní koncept.
        $groups = AdCampaignGroup::query()->where($column, true)->with(['client.adAccounts', 'client.adSettings'])->get();

        foreach ($groups as $group) {
            if (! $group->client->is_archived && ! $builder->exists($group->client, $type, $period, $group)) {
                $builder->create($group->client, $type, $period, new AdsScope($group->client, $group));
                $created++;
                $this->info("{$group->client->name} · {$group->name}: koncept {$period->label()}.");
            }
        }

        $this->info("Založeno konceptů: {$created}.");

        return self::SUCCESS;
    }
}
