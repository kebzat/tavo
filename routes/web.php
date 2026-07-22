<?php

use App\Http\Controllers\CaseStudyController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

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
