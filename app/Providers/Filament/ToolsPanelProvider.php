<?php

namespace App\Providers\Filament;

use App\Filament\Tools\Pages\Today;
use App\Http\Middleware\NoIndex;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Interní nástroje oddělené od správy obsahu webu. Sdílí codebase, databázi
 * i účty, ale nemíchá se do administrace — ta zůstává tím, co předáváme
 * správci obsahu.
 *
 * Resources a stránky proto žijí v app/Filament/Tools/, mimo adresáře,
 * které prohledává AdminPanelProvider. Jinak by se objevily v obou panelech.
 */
class ToolsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('tools')
            ->path('nastroje')
            ->login()
            ->brandName('Taveo nástroje')
            ->brandLogo(asset('images/taveo-logo-dark.svg'))
            ->darkModeBrandLogo(asset('images/taveo-logo-cream.svg'))
            ->brandLogoHeight('1.6rem')
            ->favicon(asset('favicon.svg'))
            // Písmo, cihlová a teplá šedá jako na webu. Zelená pro „v pořádku“
            // je mechová z auditů, ať panel nepůsobí jako cizí aplikace.
            ->font('Montserrat')
            ->colors([
                'primary' => Color::hex('#db4b24'),
                'gray' => Color::Stone,
                'success' => Color::hex('#4f6b3a'),
            ])
            ->navigationGroups([
                'CRM',
                'Reklamy',
                'Checklisty',
            ])
            // Po přihlášení se chodí do CRM, ne na seznam checklistů —
            // obchod je denní práce, checklisty se otevírají párkrát za zakázku.
            ->homeUrl(fn (): string => Today::getUrl(panel: 'tools'))
            ->sidebarCollapsibleOnDesktop()
            // Vlastní téma. Filament dodává jen ty utility, které používá sám;
            // stránky CRM (přehled „Dnes", kanban, grafy) stojí na vlastním
            // rozvržení, takže potřebují Tailwind sestavený nad jejich šablonami.
            ->viteTheme('resources/css/filament/tools/theme.css')
            // Interní nástroje do vyhledávačů nepatří. Meta i hlavička, protože
            // robots.txt je jen prosba a přihlašovací stránka je veřejná.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '<meta name="robots" content="noindex, nofollow, noarchive">',
            )
            ->discoverResources(in: app_path('Filament/Tools/Resources'), for: 'App\Filament\Tools\Resources')
            ->discoverPages(in: app_path('Filament/Tools/Pages'), for: 'App\Filament\Tools\Pages')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                NoIndex::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
