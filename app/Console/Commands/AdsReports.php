<?php

namespace App\Console\Commands;

use App\Enums\Ads\ReportType;
use App\Models\Client;
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

        $this->info("Založeno konceptů: {$created}.");

        return self::SUCCESS;
    }
}
