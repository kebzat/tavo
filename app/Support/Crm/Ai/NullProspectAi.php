<?php

namespace App\Support\Crm\Ai;

use App\Models\Crm\Company;

/** Bez klíče k Claude API. Proklepnutí i audit jedou jen z měření. */
class NullProspectAi implements ProspectAi
{
    public function enabled(): bool
    {
        return false;
    }

    public function lastError(): ?string
    {
        return null;
    }

    public function ping(): ?string
    {
        return 'Chybí ANTHROPIC_API_KEY (nebo se po úpravě .env neobnovila cache).';
    }

    public function judge(Company $company, array $scout): ?array
    {
        return null;
    }

    public function auditSummary(Company $company, array $scout): ?string
    {
        return null;
    }

    public function discover(string $brief, int $count, array $knownDomains): array
    {
        return [];
    }
}
