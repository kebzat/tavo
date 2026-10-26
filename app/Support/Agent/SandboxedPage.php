<?php

namespace App\Support\Agent;

use Symfony\Component\HttpFoundation\Response;

/**
 * HTML od agenta (rozbor, plán, náhled kódu) jako stránka ke čtení.
 *
 * Stránku psal agent, proto běží v sandboxu bez vlastního původu: nevidí
 * cookies nástrojů ani nic nepošle jinam. Povolená jsou jen písma z Bunny Fonts.
 */
class SandboxedPage
{
    private const CSP = "sandbox allow-scripts allow-popups allow-popups-to-escape-sandbox; default-src 'none'; "
        ."script-src 'unsafe-inline'; style-src 'unsafe-inline' https://fonts.bunny.net; font-src https://fonts.bunny.net; "
        ."img-src data:; form-action 'none'; base-uri 'none'; frame-ancestors 'self'";

    public static function response(string $html): Response
    {
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
