<?php

namespace App\Http\Controllers\Tools;

use App\Support\Agent\AgentServer;
use App\Support\Agent\AgentUnavailable;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTML z hotových podkladů agenta jako stránka ke čtení (rozbor, plán, náhled
 * kódu do Shoptetu). Odkaz jde poslat kolegovi, otevře ho jen přihlášený do
 * nástrojů (routa je v ToolsPanelProvider::authenticatedRoutes).
 *
 * Stránku psal agent, proto běží v sandboxu bez vlastního původu: nevidí
 * cookies nástrojů ani nic nepošle jinam. Povolená jsou jen písma z Bunny Fonts.
 */
class AgentHandoverPageController
{
    private const CSP = "sandbox allow-scripts allow-popups allow-popups-to-escape-sandbox; default-src 'none'; "
        ."script-src 'unsafe-inline'; style-src 'unsafe-inline' https://fonts.bunny.net; font-src https://fonts.bunny.net; "
        ."img-src data:; form-action 'none'; base-uri 'none'; frame-ancestors 'self'";

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

        // Agent často píše jen obsah stránky bez <html>. Kostru doplníme, ať se
        // česky vysází správně a v telefonu nezmenší.
        if (! preg_match('/<html[\s>]/i', $html)) {
            $html = "<!doctype html>\n<html lang=\"cs\">\n<head>\n<meta charset=\"utf-8\">\n"
                ."<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n"
                ."<meta name=\"robots\" content=\"noindex, nofollow\">\n</head>\n<body style=\"margin:0\">\n{$html}\n</body>\n</html>\n";
        }

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Security-Policy' => self::CSP,
            'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
