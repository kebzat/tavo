<?php

namespace App\Providers;

use App\Settings\ContactSettings;
use App\Settings\SiteSettings;
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

        // Zmenšeniny nahraných obrázků vznikají hned při uložení v administraci.
        ImageDerivatives::listen();
    }
}
