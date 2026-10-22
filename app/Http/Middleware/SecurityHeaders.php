<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bezpečnostní hlavičky pro celý web, administraci, nástroje i API.
 * Registrované globálně v bootstrap/app.php, protože Filament panely
 * skupinu `web` nepoužívají.
 *
 * - HSTS: prohlížeč už na taveo.cz nepůjde přes http, ani když to někdo
 *   podstrčí. Bez includeSubDomains, na subdoménách můžou běžet věci bez
 *   HTTPS, o kterých tady nevíme.
 * - frame-ancestors / X-Frame-Options: cizí web nás nevloží do rámu a nenaláká
 *   přihlášeného správce ke kliknutí na neviditelné tlačítko (clickjacking).
 * - Referrer-Policy: adresy s náhodnou částí (/klient/…, /dotaznik/…) se
 *   při prokliku na cizí web neprozradí, odejde jen doména.
 * - Permissions-Policy: kamera, mikrofon ani poloha se na webu nepoužívají.
 *
 * Plnou Content-Security-Policy záměrně nenastavujeme: Filament a Livewire
 * stojí na inline skriptech a přísná politika by panely rozbila.
 */
class SecurityHeaders
{
    private const HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Content-Security-Policy' => "frame-ancestors 'self'",
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        // Jen přes HTTPS, jinak ji prohlížeč stejně ignoruje.
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
