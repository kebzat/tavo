<?php

namespace App\Providers\Filament;

use App\Filament\Client\Pages\Auth\RequestPasswordReset;
use App\Filament\Client\Pages\Automations;
use App\Http\Middleware\NoIndex;
use Filament\Auth\MultiFactor\App\AppAuthentication;
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
 * Účet klienta na /muj-ucet: jen to, co patří jeho e-shopu (zatím
 * automatizace). Klient se nedostane do administrace ani do nástrojů,
 * správce sem smí kvůli náhledu. Přístup hlídá User::canAccessPanel().
 *
 * Vzhled jako nástroje (stejné téma). Dvoufázové ověření si klient může
 * zapnout v profilu, povinné není: účet neukazuje osobní údaje zákazníků.
 */
class ClientPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('client')
            ->path('muj-ucet')
            ->login()
            // Heslo si klient nastaví sám přes „Zapomenuté heslo“ (odkaz e-mailem).
            ->passwordReset(RequestPasswordReset::class)
            ->multiFactorAuthentication([AppAuthentication::make()->recoverable()->brandName('Taveo')])
            ->profile()
            ->brandName('Taveo')
            ->brandLogo(asset('images/taveo-logo-dark.svg'))
            ->darkModeBrandLogo(asset('images/taveo-logo-cream.svg'))
            ->brandLogoHeight('1.6rem')
            ->favicon(asset('favicon.svg'))
            ->font('Montserrat')
            ->colors([
                'primary' => Color::hex('#db4b24'),
                'gray' => Color::Stone,
                'success' => Color::hex('#4f6b3a'),
            ])
            ->homeUrl(fn (): string => Automations::getUrl(panel: 'client'))
            ->viteTheme('resources/css/filament/tools/theme.css')
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '<meta name="robots" content="noindex, nofollow, noarchive">',
            )
            ->pages([Automations::class])
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
