<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Skupiny kampaní (docs/ADS.md, Skupiny kampaní). Jeden účet často míchá
 * prodej, boosting a nábor. Skupina má vlastní cíl, rozpočet, čísla
 * a prahy upozornění, kampaň patří nejvýš do jedné skupiny.
 *
 * - ad_campaigns.status: stav kampaně z ranního stažení (běží, vypnutá…).
 * - ad_group_reach: přesný dosah skupiny za období. Dosah se přes kampaně
 *   sčítat nedá, Meta ho počítá pro výběr kampaní zvlášť. `signature` říká,
 *   pro jaké kampaně platí; po změně skupiny se do rána neukazuje.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_campaign_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('goal')->default('purchases');               // App\Enums\Ads\PrimaryGoal
            $table->decimal('monthly_budget', 12, 2)->nullable();
            $table->decimal('target_cpa', 12, 2)->nullable();
            $table->decimal('target_roas', 8, 2)->nullable();
            $table->string('name_rule')->nullable();                     // slova z názvu kampaně, čárkou: nová kampaň se zařadí sama
            $table->json('dashboard')->nullable();                       // vlastní výběr čísel, null = šablona podle cíle
            $table->json('alert_overrides')->nullable();                 // prahy jen pro skupinu
            $table->boolean('weekly_report')->default(false);
            $table->boolean('monthly_report')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->foreignId('ad_campaign_group_id')->nullable()->after('ad_account_id')->constrained()->nullOnDelete();
            $table->string('grouped_by', 10)->nullable()->after('ad_campaign_group_id'); // manual / rule
            $table->string('status', 30)->nullable()->after('objective');
        });

        Schema::create('ad_group_reach', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_campaign_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ad_account_id')->constrained()->cascadeOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->unsignedBigInteger('reach')->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->decimal('frequency', 8, 4)->default(0);
            $table->unsignedBigInteger('unique_link_clicks')->default(0);
            $table->string('signature', 64);
            $table->timestamps();

            $table->unique(['ad_campaign_group_id', 'ad_account_id', 'date_from', 'date_to'], 'ad_group_reach_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_group_reach');

        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ad_campaign_group_id');
            $table->dropColumn(['grouped_by', 'status']);
        });

        Schema::dropIfExists('ad_campaign_groups');
    }
};
