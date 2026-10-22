<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * Co smí token agenta na strojovém rozhraní CRM. Každá routa v
 * nastroje/api vyžaduje právě jedno oprávnění, viz routes/web.php.
 *
 * Agent dostane jen to, co opravdu potřebuje. Kdo píše audity cizích webů,
 * čte obsah, který mu může podstrčit kdokoli, a nemá proto umět víc než
 * audit nahrát.
 */
enum ApiAbility: string implements HasDescription, HasLabel
{
    case CompaniesScout = 'companies:scout';
    case CompaniesImport = 'companies:import';
    case AuditsImport = 'audits:import';
    case PipelineExport = 'pipeline:export';

    public function getLabel(): string
    {
        return match ($this) {
            self::CompaniesScout => 'Zjistit, jestli firma už je v CRM',
            self::CompaniesImport => 'Zakládat firmy k proklepnutí',
            self::AuditsImport => 'Nahrávat audity',
            self::PipelineExport => 'Stahovat export pipeline',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::CompaniesScout => 'GET /nastroje/api/companies/scout. Vrátí stav jedné firmy podle webu.',
            self::CompaniesImport => 'POST /nastroje/api/companies/import',
            self::AuditsImport => 'POST /nastroje/api/audits/import. Audit vznikne neveřejný.',
            self::PipelineExport => 'GET /nastroje/api/export/pipeline. Čte všechny firmy, obchody a částky.',
        };
    }
}
