<?php

namespace App\Support\Crm\Ai;

use App\Models\Crm\Company;

/**
 * Úsudek nad firmou, který z měření sám nevyleze: co firma prodává, jestli
 * je to zavedený podnik a čím ji oslovit. Bez klíče k Claude API běží
 * NullProspectAi a všechno funguje jen z měření.
 */
interface ProspectAi
{
    public function enabled(): bool;

    /**
     * @param  array<string, mixed>  $scout  měření, nálezy a skóre
     * @return array{summary: string, adjustment: int, note: string, hook: string}|null
     */
    public function judge(Company $company, array $scout): ?array;

    /**
     * Úvodní odstavec auditu. Jen z předaných nálezů, bez vymyšlených čísel.
     *
     * @param  array<string, mixed>  $scout
     */
    public function auditSummary(Company $company, array $scout): ?string;

    /**
     * Nové firmy k proklepnutí z webového vyhledávání.
     *
     * @param  list<string>  $knownDomains  co už v CRM máme, ať to nenavrhuje znovu
     * @return list<array{name: string, website: string, city: ?string, reason: string}>
     */
    public function discover(string $brief, int $count, array $knownDomains): array;
}
