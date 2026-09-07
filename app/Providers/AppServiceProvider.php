<?php

namespace App\Providers;

use App\Settings\ContactSettings;
use App\Settings\SiteSettings;
use App\Support\EshopOffers;
use App\Support\ImageDerivatives;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        /*
         * Globální nastavení sdílíme do všech Blade šablon jako $site a $contact.
         * V šablonách proto nikdy nevoláme app(SiteSettings::class) ručně.
         */
        View::composer('*', function ($view) {
            $view->with([
                'site' => app(SiteSettings::class),
                'contact' => app(ContactSettings::class),
            ]);
        });

        /*
         * Skupina „Pro e-shopy" v patičce. Nabídky nejsou v databázi, protože
         * ke každé patří vlastní routa a šablona — viz App\Support\EshopOffers.
         */
        View::composer('components.layout.footer', function ($view) {
            $view->with('eshopOffers', EshopOffers::all());
        });

        // Zmenšeniny nahraných obrázků vznikají hned při uložení v administraci.
        ImageDerivatives::listen();
    }
}
