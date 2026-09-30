<?php

/*
|--------------------------------------------------------------------------
| Interní nástroj: reklamy klientů
|--------------------------------------------------------------------------
| Denní čísla z reklamních účtů klientů (útrata, prokliky, nákupy, hodnota),
| upozornění na to, co se pokazilo, a týdenní či měsíční reporty.
|
| Ukládají se jen součty na kampaň a den. Poměry (CTR, CPA, ROAS) se počítají
| až ze součtů za zvolené období, průměr denních poměrů by lhal.
| Souhrn účtu je součet kampaní. Viz docs/ADS.md.
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('platform');                        // App\Enums\Ads\AdPlatform
            $table->string('external_id');                     // u Mety číslo účtu bez „act_“
            $table->string('name');
            $table->string('currency', 3)->default('CZK');
            $table->string('timezone')->nullable();
            $table->string('status')->nullable();              // stav účtu podle platformy (active, disabled, unsettled…)
            $table->boolean('is_active')->default(true);       // vypnutý účet se nesynchronizuje ani nehlídá
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_sync_error')->nullable();
            $table->timestamps();

            $table->unique(['platform', 'external_id']);
        });

        Schema::create('ad_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_account_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('name');
            $table->string('objective')->nullable();
            $table->timestamps();

            $table->unique(['ad_account_id', 'external_id']);
        });

        Schema::create('ad_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ad_campaign_id')->constrained()->cascadeOnDelete();
            $table->date('date');                               // den v časovém pásmu reklamního účtu
            $table->decimal('spend', 12, 2)->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('reach')->default(0);    // denní dosah, přes dny se nesčítá přesně
            $table->unsignedBigInteger('clicks')->default(0);   // všechna kliknutí
            $table->unsignedBigInteger('link_clicks')->default(0);
            $table->decimal('purchases', 12, 2)->default(0);
            $table->decimal('purchase_value', 14, 2)->default(0);
            $table->decimal('leads', 12, 2)->default(0);
            $table->decimal('add_to_cart', 12, 2)->default(0);
            $table->decimal('checkouts', 12, 2)->default(0);
            $table->json('raw')->nullable();                   // celé actions z API, ať jde později dopočítat jiná konverze
            $table->timestamps();

            $table->unique(['ad_campaign_id', 'date']);
            $table->index(['ad_account_id', 'date']);
        });

        Schema::create('ad_client_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('primary_goal')->default('purchases');  // App\Enums\Ads\PrimaryGoal
            $table->decimal('monthly_budget', 12, 2)->nullable();   // plánovaná útrata za měsíc
            $table->decimal('target_cpa', 12, 2)->nullable();
            $table->decimal('target_roas', 8, 2)->nullable();
            $table->unsignedInteger('fee_czk')->nullable();         // náš měsíční paušál, jen interně
            $table->decimal('included_hours', 5, 1)->nullable();    // hodiny v paušálu
            $table->unsignedInteger('hourly_rate')->nullable();     // sazba za práci navíc
            $table->json('report_recipients')->nullable();         // komu jde report, prázdné = kontakt klienta
            $table->boolean('weekly_report')->default(true);
            $table->boolean('monthly_report')->default(true);
            $table->text('advice')->nullable();                    // poslední návrh úprav od Clauda
            $table->timestamp('advice_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ad_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ad_account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('rule');                            // klíč pravidla, App\Support\Ads\Rules
            $table->string('fingerprint');                     // pravidlo + klient + účet: jedno živé upozornění na věc
            $table->string('severity');                        // App\Enums\Ads\AlertSeverity
            $table->string('status')->default('open');         // App\Enums\Ads\AlertStatus
            $table->string('title');
            $table->text('recommendation');
            $table->json('snapshot')->nullable();              // čísla, ze kterých pravidlo vycházelo
            $table->date('detected_on');
            $table->date('last_seen_on');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['fingerprint', 'status']);
            $table->index('status');
        });

        Schema::create('ad_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('type');                            // App\Enums\Ads\ReportType
            $table->date('period_start');
            $table->date('period_end');
            $table->string('title');
            $table->text('summary')->nullable();               // komentář Pavla v Markdownu
            $table->json('snapshot');                          // čísla zmrazená při vytvoření
            $table->string('slug')->nullable()->unique();      // /report/{slug}
            $table->string('public_token', 64)->unique();
            $table->boolean('is_public')->default(false);
            $table->string('status')->default('draft');        // App\Enums\Ads\ReportStatus
            $table->timestamp('sent_at')->nullable();
            $table->json('sent_to')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamp('first_viewed_at')->nullable();
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'period_start']);
        });

        Schema::create('ad_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_account_id')->constrained()->cascadeOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->string('status');                          // running, ok, failed
            $table->unsignedInteger('rows')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();

            $table->index(['ad_account_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_sync_runs');
        Schema::dropIfExists('ad_reports');
        Schema::dropIfExists('ad_alerts');
        Schema::dropIfExists('ad_client_settings');
        Schema::dropIfExists('ad_daily_stats');
        Schema::dropIfExists('ad_campaigns');
        Schema::dropIfExists('ad_accounts');
    }
};
