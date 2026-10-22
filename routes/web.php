<?php

use App\Enums\ApiAbility;
use App\Http\Controllers\AdReportController;
use App\Http\Controllers\Agent\AgentApiController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\CaseStudyController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\ChecklistToggleController;
use App\Http\Controllers\ClientDashboardController;
use App\Http\Controllers\Crm\AuditImportController;
use App\Http\Controllers\Crm\CandidateImportController;
use App\Http\Controllers\Crm\CompanyScoutController;
use App\Http\Controllers\Crm\PipelineExportController;
use App\Http\Controllers\EmailSignatureController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProposalController;
use App\Http\Controllers\QuestionnaireController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\VerifyAgentToken;
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

// Rozhraní pro agenta v kontejneru taveo-agent (docs/MARKETING.md). Čte data
// klientů a zapisuje jen koncepty. Vlastní token, bez session a CSRF.
Route::prefix('nastroje/api/agent')
    ->middleware([VerifyAgentToken::class, 'throttle:120,1'])
    ->withoutMiddleware([
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
    ])
    ->group(function () {
        Route::get('/klienti', [AgentApiController::class, 'clients'])->name('agent.api.clients');
        Route::get('/klienti/{slug}', [AgentApiController::class, 'client'])->name('agent.api.client');
        Route::post('/klienti/{slug}/prehled', [AgentApiController::class, 'briefing'])->name('agent.api.briefing');
        Route::post('/klienti/{slug}/ukoly', [AgentApiController::class, 'tasks'])->name('agent.api.tasks');
        Route::post('/klienti/{slug}/rozhodnuti', [AgentApiController::class, 'decision'])->name('agent.api.decision');
        Route::post('/reporty/{report}', [AgentApiController::class, 'report'])->name('agent.api.report');
    });

// Strojové rozhraní interního CRM. Bez session a CSRF — volá ho automatizace,
// ne prohlížeč — a ověřené tokenem agenta s oprávněním pro danou routu
// (Nástroje → Přístupy agentů). Musí být nad catch-all routou /{slug} níž,
// i když by ji dvousegmentová adresa stejně minula.
Route::prefix('nastroje/api')
    ->withoutMiddleware([
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
    ])
    ->group(function () {
        Route::post('/audits/import', AuditImportController::class)
            ->middleware([VerifyCrmToken::class.':'.ApiAbility::AuditsImport->value, 'throttle:20,1'])
            ->name('crm.audits.import');

        Route::post('/companies/import', CandidateImportController::class)
            ->middleware([VerifyCrmToken::class.':'.ApiAbility::CompaniesImport->value, 'throttle:60,1'])
            ->name('crm.companies.import');

        Route::get('/companies/scout', CompanyScoutController::class)
            ->middleware([VerifyCrmToken::class.':'.ApiAbility::CompaniesScout->value, 'throttle:120,1'])
            ->name('crm.companies.scout');

        Route::get('/export/pipeline', PipelineExportController::class)
            ->middleware([VerifyCrmToken::class.':'.ApiAbility::PipelineExport->value, 'throttle:60,1'])
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

// Sdílený technický checklist klienta. Adresa je čitelný slug klienta,
// starší odkazy s náhodným tokenem se na něj přesměrují. Obsah není citlivý,
// ale do vyhledávačů nepatří (noindex + robots.txt).
// Odškrtávat smí každý, kdo zná odkaz, viz ChecklistToggleController.
Route::get('/checklist/{key}', [ChecklistController::class, 'show'])->name('checklist.show');
Route::post('/checklist/{key}/polozka/{item}', ChecklistToggleController::class)
    ->middleware('throttle:120,1')
    ->name('checklist.toggle');
Route::get('/checklist/{key}/{slug}', [ChecklistController::class, 'category'])->name('checklist.category');

// Audit klientského webu. Sdílí se stejně jako checklist a oba na sebe odkazují.
Route::get('/audit/{key}', AuditController::class)->name('audit.show');

// Report reklam pro klienta. Sdílí se jako audit: čitelná adresa, noindex.
Route::get('/report/{key}', AdReportController::class)->name('ad-report.show');

// Přehled spolupráce pro klienta na paušál: hodiny, hotová práce, plán.
// Ukazuje peníze, proto má adresa za názvem klienta náhodnou část
// (/klient/svet-cejlonu-k7f2q9). Omezení počtu pokusů brání ji hádat.
Route::get('/klient/{token}', ClientDashboardController::class)
    ->middleware('throttle:60,1')
    ->name('client-dashboard.show');

// Potenciální spolupráce: dopadová stránka pro firmu, kterou chceme získat.
Route::get('/potencialni-spoluprace/{slug}', ProposalController::class)->name('proposal.show');

// Dotazník (pro Pavla, pro klienta před spoluprací). Adresa má náhodnou část
// jako přehled klienta. Ukládání je bez CSRF: oprávnění dává adresa, ne
// přihlášení, a rozepsané odpovědi nesmí shodit vypršelá session.
Route::get('/dotaznik/{slug}', [QuestionnaireController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('questionnaire.show');
Route::post('/dotaznik/{slug}', [QuestionnaireController::class, 'save'])
    ->middleware('throttle:60,1')
    ->withoutMiddleware(PreventRequestForgery::class)
    ->name('questionnaire.save');

// Náhled e-mailového podpisu. Jen lokálně, na ostrém webu nemá co dělat.
if (app()->isLocal()) {
    Route::get('/podpis-emailu', EmailSignatureController::class)->name('email-signature');
}

// Statické stránky (GDPR, cookies…) a nabídky pro e-shopy (/mereni-pro-eshopy…).
// Musí zůstat poslední, chytá volný slug. Rozdělení viz PageController.
Route::get('/{slug}', [PageController::class, 'show'])->name('pages.show');
