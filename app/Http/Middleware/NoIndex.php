<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zakáže indexaci odpovědi.
 *
 * Panel interních nástrojů je za přihlášením a `robots.txt` ho zakazuje, ale
 * ani jedno není záruka: soubor robots.txt vyhledávače jen prosí a adresa se
 * dá vyzradit odkazem odjinud. Hlavička je jediné, co platí i pro stránku,
 * kterou robot dostane do ruky.
 */
class NoIndex
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

        return $response;
    }
}
