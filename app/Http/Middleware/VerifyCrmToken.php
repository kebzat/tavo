<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ověření sdíleného tokenu u strojových endpointů CRM.
 *
 * Nenastavený token endpointy vypíná úplně. Kdyby chybějící hodnota znamenala
 * „bez ověření", stačilo by na produkci zapomenout jeden řádek v .env a data
 * by byla venku.
 */
class VerifyCrmToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('crm.import_token');

        if ($expected === '') {
            Log::warning('Strojový endpoint CRM byl volán, ale CRM_IMPORT_TOKEN není nastavený.');

            abort(404);
        }

        // Token může přijít hlavičkou (běžný klient) i v adrese (jednoduché
        // stažení exportu). Porovnává se v konstantním čase.
        $provided = (string) ($request->header('X-Crm-Token')
            ?? $request->bearerToken()
            ?? $request->query('token', ''));

        if (! hash_equals($expected, $provided)) {
            abort(401, 'Neplatný token.');
        }

        return $next($request);
    }
}
