<?php

/*
|--------------------------------------------------------------------------
| Automatizace marketingu (podle dotazníku Pavla, 22. 10. 2026)
|--------------------------------------------------------------------------
| - karta klienta („mozek“): cíle, KPI, rozsah, omezení, schvalování, tón,
|   kontakty, rozhodnutí se zdrojem a platností, cílové stránky, vyúčtování
| - ranní přehledy od agenta (client_briefings)
| - zpětná vazba ke kvalitě leadů (lead_feedback)
| - paměť kreativních testů (creative_tests)
| - kontrola cílových stránek (landing_checks)
| - úkoly: návrhy od agenta, priorita, termín, na co se čeká, odkud úkol je
| - reklamy: interakce (boosting), nastavení upozornění pro klienta,
|   zamítnuté reklamy na účtu, report rozdělený na fakta, příčiny, doporučení
| Viz docs/MARKETING.md.
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Interakce s příspěvkem, jak je Meta vrací v actions (boosting). */
    private const ENGAGEMENT_TYPES = ['post_engagement', 'page_engagement'];

    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->json('profile')->nullable()->after('note');          // karta klienta: cíle, KPI, rozsah, omezení, schvalování, tón
            $table->json('contacts')->nullable()->after('profile');      // [{name, role, email, phone, note}]
            $table->json('decisions')->nullable()->after('contacts');    // [{date, decision, source, valid_until}]
            $table->json('landing_pages')->nullable()->after('decisions'); // adresy, které se každé ráno kontrolují
            $table->json('billing_statement')->nullable()->after('landing_pages'); // jak chce klient vyúčtování hodin
        });

        Schema::create('client_briefings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->text('body');                                        // Markdown od agenta
            $table->json('sources')->nullable();                         // z čeho vycházel (data, čerstvost)
            $table->string('author')->nullable();                        // agent, nebo člověk
            $table->timestamps();

            $table->unique(['client_id', 'date']);
        });

        Schema::create('lead_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->date('period_from');
            $table->date('period_to');
            $table->unsignedInteger('leads')->nullable();                // kolik leadů klient eviduje
            $table->unsignedInteger('contacted')->nullable();            // dovolali se / odpověděl
            $table->unsignedInteger('qualified')->nullable();            // relevantní
            $table->unsignedInteger('deals')->nullable();                // uzavřené obchody
            $table->decimal('deal_value', 12, 2)->nullable();
            $table->string('source')->nullable();                        // CRM klienta, tabulka, telefonát
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'period_to']);
        });

        Schema::create('creative_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->text('hypothesis')->nullable();                      // co a proč testujeme
            $table->string('argument')->nullable();                      // hlavní argument / nabídka
            $table->string('hook')->nullable();                          // úvodní věta, první vteřiny videa
            $table->string('format')->nullable();                        // reels, carousel, statický banner, UGC…
            $table->string('landing_url')->nullable();
            $table->decimal('budget', 12, 2)->nullable();
            $table->text('result')->nullable();                          // čísla a pozorování
            $table->string('verdict', 20)->default('running');           // App\Enums\Ads\CreativeVerdict
            $table->text('learning')->nullable();                        // co z toho plyne pro další zadání
            $table->timestamps();

            $table->index(['client_id', 'verdict']);
        });

        Schema::create('landing_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('url', 500);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->boolean('ok')->default(false);
            $table->boolean('has_meta_pixel')->nullable();
            $table->unsignedInteger('response_ms')->nullable();
            $table->string('error')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->unique(['client_id', 'url']);
        });

        Schema::table('client_tasks', function (Blueprint $table) {
            $table->boolean('is_proposal')->default(false)->after('status'); // návrh od agenta, čeká na schválení
            $table->string('priority', 10)->nullable()->after('is_proposal'); // high / normal / low
            $table->date('due_on')->nullable()->after('planned_for');
            $table->string('waiting_for')->nullable()->after('due_on');      // na koho nebo na jaké podklady se čeká
            $table->string('source')->nullable()->after('waiting_for');      // odkud úkol vzešel (schůzka 22. 10., e-mail…)
        });

        Schema::table('ad_daily_stats', function (Blueprint $table) {
            $table->decimal('engagements', 12, 2)->default(0)->after('landing_page_views');
        });

        Schema::table('ad_client_settings', function (Blueprint $table) {
            $table->json('alert_overrides')->nullable()->after('advice_at'); // prahy a ztlumená pravidla jen pro klienta
        });

        Schema::table('ad_accounts', function (Blueprint $table) {
            $table->json('issues')->nullable();                              // zamítnuté a problémové reklamy z ranní kontroly
            $table->timestamp('issues_checked_at')->nullable();
        });

        Schema::table('ad_reports', function (Blueprint $table) {
            $table->text('facts')->nullable()->after('summary');             // co se stalo (jen z čísel)
            $table->text('causes')->nullable()->after('facts');              // možné příčiny (hypotézy)
            $table->text('recommendations')->nullable()->after('causes');    // co doporučujeme
            $table->json('sources')->nullable()->after('snapshot');          // odkud a jak čerstvá jsou data
        });

        $this->backfillEngagements();
    }

    public function down(): void
    {
        Schema::table('ad_reports', fn (Blueprint $table) => $table->dropColumn(['facts', 'causes', 'recommendations', 'sources']));
        Schema::table('ad_accounts', fn (Blueprint $table) => $table->dropColumn(['issues', 'issues_checked_at']));
        Schema::table('ad_client_settings', fn (Blueprint $table) => $table->dropColumn('alert_overrides'));
        Schema::table('ad_daily_stats', fn (Blueprint $table) => $table->dropColumn('engagements'));
        Schema::table('client_tasks', fn (Blueprint $table) => $table->dropColumn(['is_proposal', 'priority', 'due_on', 'waiting_for', 'source']));
        Schema::dropIfExists('landing_checks');
        Schema::dropIfExists('creative_tests');
        Schema::dropIfExists('lead_feedback');
        Schema::dropIfExists('client_briefings');
        Schema::table('clients', fn (Blueprint $table) => $table->dropColumn(['profile', 'contacts', 'decisions', 'landing_pages', 'billing_statement']));
    }

    /** Interakce ze surových actions, které se ukládají od začátku (jako u landing_page_views). */
    private function backfillEngagements(): void
    {
        DB::table('ad_daily_stats')
            ->whereNotNull('raw')
            ->select(['id', 'raw'])
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $actions = json_decode((string) $row->raw, true)['actions'] ?? null;

                    if (! is_array($actions)) {
                        continue;
                    }

                    foreach (self::ENGAGEMENT_TYPES as $type) {
                        if (($actions[$type] ?? 0) > 0) {
                            DB::table('ad_daily_stats')->where('id', $row->id)->update(['engagements' => (float) $actions[$type]]);

                            break;
                        }
                    }
                }
            });
    }
};
