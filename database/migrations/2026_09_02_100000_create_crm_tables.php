<?php

/*
|--------------------------------------------------------------------------
| Interní nástroj: obchodní CRM
|--------------------------------------------------------------------------
| Druhý nástroj v panelu /nastroje, vedle checklistů. Neslouží webu Taveo,
| ale nám dvěma: koho oslovujeme, co jsme mu poslali, kdy se ozvat znovu
| a jak to dopadlo.
|
| Kostra: firma → kontakty, obchody a aktivity.
| Firma je subjekt, obchod je jedna příležitost v čase (jedna firma jich může
| mít víc za sebou) a aktivita je záznam čehokoli, co jsme udělali.
|
| Poptávky z portálů stojí zvlášť. Přitékají strojově a většina z nich nikdy
| nedojde do fáze, kdy má smysl zakládat firmu — proto vlastní tabulka
| s volitelnou vazbou na firmu, ne rovnou firma s podivným stavem.
|
| Enumy se ukládají jako string, ne jako MySQL ENUM. Přidání hodnoty je pak
| změna v PHP, ne migrace tabulky, což je u obchodních číselníků častý případ.
*/

use App\Enums\Crm\CompanyStatus;
use App\Enums\Crm\DealStage;
use App\Enums\Crm\DemandStatus;
use App\Enums\Crm\Priority;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('website')->nullable();

            // Doména bez www a protokolu. Drží se zvlášť kvůli hledání duplicit —
            // porovnávat celé URL nejde, „example.cz" a „https://www.example.cz/"
            // je tatáž firma. Plní se automaticky v modelu z `website`.
            $table->string('domain')->nullable()->index();

            $table->string('city')->nullable();
            $table->string('industry')->nullable();
            $table->string('segment', 30)->index();          // App\Enums\Crm\CompanySegment
            $table->string('platform')->nullable();          // Shoptet, WooCommerce, Upgates…
            $table->text('pain')->nullable();                // konkrétní pozorovaná bolest
            $table->text('offer')->nullable();               // co jí nabízíme
            $table->string('reference_to_use')->nullable();  // kterou referencí argumentovat
            $table->string('priority', 5)->default(Priority::B->value)->index();
            $table->string('source', 30)->index();           // App\Enums\Crm\CompanySource
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();

            // Kdy se máme ozvat. Přepočítává se z follow-upů na aktivitách,
            // viz App\Observers\Crm\ActivityObserver.
            $table->dateTime('next_action_at')->nullable()->index();

            // Kdy jsme s firmou naposledy něco dělali. Dá se dopočítat z aktivit,
            // ale filtr „bez pohybu 7+ dní" a řazení seznamu by kvůli tomu musely
            // pokaždé joinovat a agregovat celou tabulku aktivit.
            $table->dateTime('last_activity_at')->nullable()->index();

            $table->string('status', 20)->default(CompanyStatus::New->value)->index();
            $table->string('lost_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('crm_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('crm_companies')->cascadeOnDelete();
            $table->string('name')->nullable();               // z rešerše často neznáme
            $table->string('role')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('crm_deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('crm_companies')->cascadeOnDelete();
            $table->string('title');
            $table->string('package', 40);                    // App\Enums\Crm\DealPackage
            $table->unsignedInteger('value_czk')->nullable();
            $table->string('stage', 20)->default(DealStage::Lead->value)->index();

            // Výchozí hodnota podle fáze, ale ručně přepsatelná — u konkrétního
            // obchodu víme víc než tabulka.
            $table->unsignedTinyInteger('probability')->default(5);

            // Kdy obchod naposledy změnil fázi. Karta v pipeline z toho počítá
            // „dny ve fázi", což je jediný způsob, jak si všimnout ležáků.
            $table->dateTime('stage_changed_at')->nullable();

            $table->date('expected_close_at')->nullable();

            // Kdy odešla nabídka. Odvodit se to z `stage_changed_at` nedá —
            // jakmile se obchod pohne dál, čas změny fáze se přepíše a týdenní
            // KPI „odeslané nabídky" by zpětně o ten obchod přišlo.
            $table->dateTime('proposal_sent_at')->nullable()->index();

            $table->dateTime('won_at')->nullable();
            $table->dateTime('lost_at')->nullable();
            $table->string('lost_reason')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('crm_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('crm_companies')->cascadeOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained('crm_deals')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20)->index();               // App\Enums\Crm\ActivityType
            $table->string('subject');
            $table->text('body')->nullable();                  // co jsme poslali nebo řekli
            $table->dateTime('happened_at')->index();

            // Vyplněný follow-up se propíše do firmy jako next_action_at.
            $table->dateTime('follow_up_at')->nullable()->index();

            $table->string('outcome', 20)->nullable();         // App\Enums\Crm\ActivityOutcome
            $table->timestamps();
        });

        Schema::create('crm_demands', function (Blueprint $table) {
            $table->id();
            $table->string('source', 30)->index();             // App\Enums\Crm\DemandSource

            // Adresa poptávky je zároveň její identita. Ranní import podle ní
            // rozhoduje, jestli řádek zakládá, nebo aktualizuje.
            $table->string('url')->unique();

            $table->string('title');
            $table->text('summary')->nullable();
            $table->date('posted_at')->nullable()->index();
            $table->string('budget_estimate')->nullable();     // volný text, portály uvádějí rozpětí
            $table->string('priority', 5)->default(Priority::B->value)->index();
            $table->string('status', 20)->default(DemandStatus::New->value)->index();
            $table->dateTime('replied_at')->nullable();
            $table->foreignId('company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('channel', 20)->index();            // App\Enums\Crm\TemplateChannel
            $table->string('subject')->nullable();
            $table->text('body');                              // s placeholdery {{firma}}, {{jmeno}}…
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('crm_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('crm_company_tag', function (Blueprint $table) {
            $table->foreignId('company_id')->constrained('crm_companies')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('crm_tags')->cascadeOnDelete();
            $table->primary(['company_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_company_tag');
        Schema::dropIfExists('crm_tags');
        Schema::dropIfExists('crm_message_templates');
        Schema::dropIfExists('crm_demands');
        Schema::dropIfExists('crm_activities');
        Schema::dropIfExists('crm_deals');
        Schema::dropIfExists('crm_contacts');
        Schema::dropIfExists('crm_companies');
    }
};
