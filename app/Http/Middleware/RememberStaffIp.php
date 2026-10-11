<?php

namespace App\Http\Middleware;

use App\Support\OurIps;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zapamatuje si adresu, ze které je někdo z nás přihlášený. Otevření
 * sdíleného dokumentu z ní se pak klientovi nepočítá, ani když ho
 * otevřeme nepřihlášení z mobilu na stejné wifi. Zápis jednou za hodinu.
 */
class RememberStaffIp
{
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();

        if ($request->user() !== null && $ip !== null && Cache::add('staff-ip:'.$ip, true, now()->addHour())) {
            app(OurIps::class)->remember($ip, $request->user()->getKey());
        }

        return $next($request);
    }
}
