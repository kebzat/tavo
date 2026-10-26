<?php

namespace App\Http\Controllers\Tools;

use App\Support\Agent\AgentServer;
use App\Support\Agent\AgentUnavailable;
use App\Support\Agent\SandboxedPage;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTML z hotových podkladů agenta jako stránka ke čtení (rozbor, plán, náhled
 * kódu do Shoptetu). Pro přihlášené do nástrojů (routa je
 * v ToolsPanelProvider::authenticatedRoutes), nebo s podepsaným odkazem na 30 dní.
 * Podklady po „Hotovo“ zmizí i s odkazem; co má vydržet, patří do archivu
 * klienta ({@see ArchivePageController}).
 */
class AgentHandoverPageController
{
    public function __invoke(AgentServer $agent, string $project, string $name, string $file): Response
    {
        abort_unless(preg_match('/^[a-z0-9][a-z0-9-]{1,40}$/', $project) && preg_match('/^[a-z0-9][a-z0-9._-]{0,60}$/', $name), 404);
        abort_unless(preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,80}\.html?$/i', $file), 404);

        try {
            $handovers = $agent->handovers($project);
        } catch (AgentUnavailable) {
            abort(503);
        }

        $html = collect($handovers)->firstWhere('jmeno', $name)['kody'] ?? [];
        $html = collect($html)->firstWhere('soubor', $file)['obsah'] ?? null;

        abort_if(blank($html), 404);

        return SandboxedPage::response($html);
    }
}
