<?php

namespace App\Console\Commands;

use App\Enums\Crm\CompanySegment;
use App\Enums\Crm\CompanySource;
use App\Enums\Crm\CompanyStatus;
use App\Enums\Crm\FitVerdict;
use App\Models\Crm\Company;
use App\Support\Crm\Ai\ProspectAi;
use App\Support\Crm\Domain;
use App\Support\Crm\Scout\ProspectScout;
use Illuminate\Console\Command;

/**
 * Hledání nových firem k oslovení.
 *
 * Claude přes webové vyhledávání navrhne firmy podle zadání, každá se
 * hned proklepne a do fronty k oslovení se dostanou jen ty, které projdou
 * skóre. Ostatní se založí rovnou odložené, ať je příští hledání nenavrhne
 * znovu. Bez klíče k Claude API příkaz nic nedělá.
 */
class CrmDiscover extends Command
{
    /** Výchozí zadání podle segmentů s nejvyšší prioritou v docs/BRAND-STRATEGY.md. */
    public const DEFAULT_BRIEF = 'Zavedené menší a střední české e-shopy (obrat zhruba 5 až 100 milionů Kč) na Shoptetu, WooCommerce, Shopify nebo Upgates, '
        .'které platí reklamu a mají co zlepšit na webu. Přednost mají Královéhradecký a Pardubický kraj, ale celá ČR je v pořádku. '
        .'Vynech velké hráče s vlastním vývojem, dropshipping bez vlastní značky a marketplace.';

    protected $signature = 'crm:discover
        {--brief= : Koho hledat, volným textem}
        {--count=15 : Kolik firem navrhnout}
        {--dry-run : Jen vypíše návrhy, nic nezakládá}';

    protected $description = 'Najde přes Claude nové firmy k oslovení, proklepne je a dobré zařadí do fronty';

    public function handle(ProspectAi $ai, ProspectScout $scout): int
    {
        if (! $ai->enabled()) {
            $this->warn('Chybí ANTHROPIC_API_KEY, hledání se nespustí.');

            return self::SUCCESS;
        }

        $known = Company::withTrashed()->whereNotNull('domain')->pluck('domain')->all();
        $candidates = $ai->discover($this->option('brief') ?: self::DEFAULT_BRIEF, (int) $this->option('count'), $known);

        if ($candidates === []) {
            $this->warn('Claude nic nenavrhl.');

            return self::SUCCESS;
        }

        $queued = 0;

        foreach ($candidates as $candidate) {
            $domain = Domain::normalize($candidate['website']);

            if ($domain === null || in_array($domain, $known, true)) {
                continue;
            }

            $known[] = $domain;

            if ($this->option('dry-run')) {
                $this->line("{$candidate['name']} · {$domain} · {$candidate['reason']}");

                continue;
            }

            $company = Company::create([
                'name' => $candidate['name'],
                'website' => $candidate['website'],
                'city' => $candidate['city'],
                'segment' => CompanySegment::Eshop,
                'source' => CompanySource::Research,
                'status' => CompanyStatus::New,
                'notes' => 'Navrhl Claude '.now()->format('j. n. Y').': '.$candidate['reason'],
            ]);

            $scout->scout($company);
            $scout->parkIfRejected($company);

            $ok = $company->fit_verdict === FitVerdict::Strong || $company->fit_verdict === FitVerdict::Maybe;
            $queued += (int) $ok;

            $this->line(sprintf('%3d  %-16s %s', $company->fit_score, $company->fit_verdict->getLabel(), $company->name));
        }

        $this->info("Do fronty k oslovení přibylo {$queued} firem.");

        return self::SUCCESS;
    }
}
