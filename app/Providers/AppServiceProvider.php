<?php

namespace App\Providers;

use App\Models\Founder;
use App\Settings\ContactSettings;
use App\Settings\SiteSettings;
use App\Support\Ads\Ai\AdsAdvisor;
use App\Support\Ads\Ai\ClaudeAdsAdvisor;
use App\Support\Ads\Ai\NullAdsAdvisor;
use App\Support\Crm\Ai\ClaudeProspectAi;
use App\Support\Crm\Ai\NullProspectAi;
use App\Support\Crm\Ai\ProspectAi;
use App\Support\EshopOffers;
use App\Support\ImageDerivatives;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Claude jen s klíčem a zapnutým ANTHROPIC_ENABLED. Jinak proklepnutí
        // webu a audit jedou z měření, nic se nerozbije a nic se neplatí.
        $this->app->bind(ProspectAi::class, fn (): ProspectAi => config('services.anthropic.enabled') && filled(config('services.anthropic.key'))
            ? new ClaudeProspectAi(
                config('services.anthropic.key'),
                config('services.anthropic.model'),
                config('services.anthropic.workspace_id'),
            )
            : new NullProspectAi);

        // Návrh úprav reklam: stejný vypínač jako u CRM.
        $this->app->bind(AdsAdvisor::class, fn (): AdsAdvisor => config('services.anthropic.enabled') && filled(config('services.anthropic.key'))
            ? new ClaudeAdsAdvisor(
                config('services.anthropic.key'),
                config('services.anthropic.model'),
                config('services.anthropic.workspace_id'),
            )
            : new NullAdsAdvisor);
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

        /*
         * Telefony na Pavla a Toma pro spěchající. Komponenta si je nesmí tahat
         * sama — v šabloně nemá být dotaz do databáze.
         */
        View::composer('components.contact-people', function ($view) {
            $view->with('contactPeople', Founder::callable()->ordered()->get());
        });

        // Zmenšeniny nahraných obrázků vznikají hned při uložení v administraci.
        ImageDerivatives::listen();
    }
}
