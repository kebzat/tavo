<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * Chybu vracíme jako JSON jen tam, kde na druhé straně čeká program:
         * strojové rozhraní a poptávkový formulář odeslaný na pozadí. Zbytek
         * webu musí dostat normální stránku, jinak by návštěvníkovi místo
         * hlášky vyskočil výpis JSONu.
         *
         * Bez druhé podmínky se chyby z formuláře vrátí jako přesměrování
         * a odeslání na pozadí nemá co ukázat u polí.
         */
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
                || ($request->is('poptavka') && $request->expectsJson()),
        );
    })->create();
