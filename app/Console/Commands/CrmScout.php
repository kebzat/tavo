<?php

namespace App\Console\Commands;

use App\Enums\Crm\CompanyStatus;
use App\Models\Crm\Company;
use App\Support\Crm\Scout\ProspectScout;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Proklepnutí webů firem v dávce.
 *
 * Plánovač ho pouští každé ráno na nové firmy bez skóre, takže cokoli,
 * co přiteče importem nebo přes API, má do rána posouzení. Ručně se hodí
 * na úklid starší rešerše: `crm:scout --new --park`.
 */
class CrmScout extends Command
{
    protected $signature = 'crm:scout
        {firmy?* : ID nebo domény konkrétních firem}
        {--new : Všechny firmy ve stavu „Nová"}
        {--unscored : Jen firmy, které ještě nikdo neproklepnul}
        {--older-than= : Jen firmy proklepnuté před víc než N dny}
        {--park : Nevhodné firmy z rešerše rovnou odložit}
        {--limit=100 : Nejvýš tolik firem najednou}';

    protected $description = 'Proklepne weby firem, spočítá skóre a nevhodné z rešerše případně odloží';

    public function handle(ProspectScout $scout): int
    {
        $companies = $this->query()->limit((int) $this->option('limit'))->get();

        if ($companies->isEmpty()) {
            $this->info('Není co proklepnout.');

            return self::SUCCESS;
        }

        $rows = [];
        $parked = 0;

        foreach ($companies as $company) {
            try {
                $scout->scout($company);
            } catch (Throwable $e) {
                // Jeden rozbitý web nesmí zastavit celou dávku.
                $this->warn("{$company->name}: {$e->getMessage()}");

                continue;
            }

            $wasParked = $this->option('park') && $scout->parkIfRejected($company);
            $parked += (int) $wasParked;

            $rows[] = [
                $company->fit_score,
                $company->fit_verdict->getLabel(),
                $company->name,
                $company->domain,
                $company->platform,
                $wasParked ? 'odloženo' : '',
            ];

            $this->line(sprintf('%3d  %-16s %s', $company->fit_score, $company->fit_verdict->getLabel(), $company->name));
        }

        usort($rows, fn (array $a, array $b): int => $b[0] <=> $a[0]);

        $this->newLine();
        $this->table(['Skóre', 'Verdikt', 'Firma', 'Web', 'Platforma', ''], $rows);
        $this->info(count($rows).' proklepnuto'.($this->option('park') ? ", {$parked} odloženo." : '.'));

        return self::SUCCESS;
    }

    private function query(): Builder
    {
        $ids = $this->argument('firmy');

        return Company::query()
            ->whereNotNull('website')
            ->when($ids !== [], fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->whereIn('id', array_filter($ids, 'is_numeric'))
                ->orWhereIn('domain', $ids)))
            ->when($this->option('new'), fn (Builder $q) => $q->where('status', CompanyStatus::New))
            ->when($this->option('unscored'), fn (Builder $q) => $q->whereNull('scouted_at'))
            ->when($this->option('older-than'), fn (Builder $q, $days) => $q->where(fn (Builder $q) => $q
                ->whereNull('scouted_at')
                ->orWhere('scouted_at', '<', now()->subDays((int) $days))))
            ->orderBy('id');
    }
}
