<?php

use App\Http\Controllers\CaseStudyController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\ChecklistToggleController;
use App\Http\Controllers\Crm\DemandImportController;
use App\Http\Controllers\Crm\PipelineExportController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\VerifyCrmToken;
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

// Strojové rozhraní interního CRM. Bez session a CSRF — volá ho automatizace,
// ne prohlížeč — a ověřené sdíleným tokenem z .env. Musí být nad catch-all
// routou /{slug} níž, i když by ji dvousegmentová adresa stejně minula.
Route::prefix('nastroje/api')
    ->middleware(VerifyCrmToken::class)
    ->withoutMiddleware([
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
    ])
    ->group(function () {
        Route::post('/demands/import', DemandImportController::class)
            ->middleware('throttle:60,1')
            ->name('crm.demands.import');

        Route::get('/export/pipeline', PipelineExportController::class)
            ->middleware('throttle:60,1')
            ->name('crm.export.pipeline');
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

// Sdílený technický checklist klienta. Chráněný jen náhodným tokenem —
// obsah není citlivý, ale do vyhledávačů nepatří (noindex + robots.txt).
// Odškrtávat smí každý, kdo zná odkaz, viz ChecklistToggleController.
Route::get('/checklist/{token}', [ChecklistController::class, 'show'])->name('checklist.show');
Route::post('/checklist/{token}/polozka/{item}', ChecklistToggleController::class)
    ->middleware('throttle:120,1')
    ->name('checklist.toggle');
Route::get('/checklist/{token}/{slug}', [ChecklistController::class, 'category'])->name('checklist.category');

// Statické stránky (GDPR, cookies…) — musí zůstat poslední, chytá volný slug.
Route::get('/{slug}', [PageController::class, 'show'])->name('pages.show');
