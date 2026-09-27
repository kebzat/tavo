<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Strojové endpointy odpovídají vždy JSONem, i když klient nepošle
 * `Accept: application/json`. Jinak by chybná data skončila přesměrováním
 * na úvodní stránku a automatizace by nevěděla, co je špatně.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
