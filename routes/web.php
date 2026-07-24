<?php

use App\Http\Controllers\CaseStudyController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SitemapController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// Průvodce instalací. Musí být první (jinak ho spolkne catch-all /{slug} níž)
// a bez session/CSRF — na čerstvém serveru ještě není APP_KEY a šifrování
// cookies by spadlo dřív, než by se průvodce vůbec zobrazil.
// Po dokončení instalace se sám zamkne a vrací 404.
Route::withoutMiddleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    ShareErrorsFromSession::class,
    PreventRequestForgery::class,
])->group(function () {
    Route::get('/install', [InstallController::class, 'show'])->name('install.show');
    Route::post('/install', [InstallController::class, 'run'])->name('install.run');
});

Route::get('/', HomeController::class)->name('home');

Route::get('/reference', [CaseStudyController::class, 'index'])->name('cases.index');
Route::get('/reference/{slug}', [CaseStudyController::class, 'show'])->name('cases.show');

Route::get('/sluzby/{slug}', [ServiceController::class, 'show'])->name('services.show');

Route::get('/sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

Route::post('/poptavka', LeadController::class)
    ->middleware('throttle:5,1')
    ->name('lead.store');

// Statické stránky (GDPR, cookies…) — musí zůstat poslední, chytá volný slug.
Route::get('/{slug}', [PageController::class, 'show'])->name('pages.show');
