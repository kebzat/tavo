<?php

namespace App\Support\Crm;

use App\Enums\Crm\ActivityOutcome;
use App\Enums\Crm\CompanySegment;
use App\Enums\Crm\CompanySource;
use App\Enums\Crm\CompanyStatus;
use App\Models\Crm\Company;
use Illuminate\Support\Collection;

/**
 * Který kanál a segment doopravdy sype.
 *
 * Na rozdíl od týdenních KPI se počítá přes celou historii — konverzní poměr
 * kanálu se z jednoho týdne poznat nedá. Zajímá nás poměr, ne absolutní čísla:
 * padesát firem z rešerše se dvěma odpověďmi je horší kanál než pět doporučení
 * se třemi.
 */
class ChannelBreakdown
{
    /**
     * Rozpad podle zdroje firmy.
     *
     * @return Collection<int, array{label: string, companies: int, replies: int, won: int, rate: float}>
     */
    public static function bySource(): Collection
    {
        return self::rows('source', fn (string $value): string => (CompanySource::tryFrom($value)?->getLabel() ?? $value));
    }

    /**
     * Rozpad podle segmentu.
     *
     * @return Collection<int, array{label: string, companies: int, replies: int, won: int, rate: float}>
     */
    public static function bySegment(): Collection
    {
        return self::rows('segment', fn (string $value): string => (CompanySegment::tryFrom($value)?->getLabel() ?? $value));
    }

    /**
     * @param  callable(string): string  $label
     * @return Collection<int, array{label: string, companies: int, replies: int, won: int, rate: float}>
     */
    private static function rows(string $column, callable $label): Collection
    {
        // Odpověď poznáme podle výsledku některé aktivity, nebo podle toho,
        // že se firma dostala aspoň do stavu „Odpověděli". Jedno bez druhého
        // by část odpovědí přehlédlo — viz komentář ve WeeklyKpi::replies().
        $answered = array_column(ActivityOutcome::answered(), 'value');
        $repliedOrFurther = [
            CompanyStatus::Replied->value,
            CompanyStatus::Call->value,
            CompanyStatus::Proposal->value,
            CompanyStatus::Won->value,
        ];

        return Company::query()
            ->groupBy($column)
            ->selectRaw($column.' as bucket')
            ->selectRaw('count(*) as companies')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as won', [CompanyStatus::Won->value])
            ->selectRaw(
                'sum(case when status in ('.implode(',', array_fill(0, count($repliedOrFurther), '?')).')'
                .' or exists (select 1 from crm_activities where crm_activities.company_id = crm_companies.id'
                .' and crm_activities.outcome in ('.implode(',', array_fill(0, count($answered), '?')).'))'
                .' then 1 else 0 end) as replies',
                [...$repliedOrFurther, ...$answered]
            )
            ->orderByDesc('companies')
            ->get()
            ->map(function ($row) use ($label): array {
                $companies = (int) $row->companies;
                $replies = (int) $row->replies;

                return [
                    'label' => $label((string) $row->bucket),
                    'companies' => $companies,
                    'replies' => $replies,
                    'won' => (int) $row->won,
                    'rate' => $companies > 0 ? round($replies / $companies * 100, 1) : 0.0,
                ];
            });
    }
}
