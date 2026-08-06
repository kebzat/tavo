<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
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
            ->favicon(asset('favicon.svg'))
            ->colors([
                'primary' => Color::hex('#db4b24'),
            ])
            ->navigationGroups([
                'Checklisty',
            ])
            ->sidebarCollapsibleOnDesktop()
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
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
