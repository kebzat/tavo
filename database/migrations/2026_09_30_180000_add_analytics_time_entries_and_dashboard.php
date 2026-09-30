<?php

/*
|--------------------------------------------------------------------------
| Reklamy: analytika webu, hodiny nad paušál, vlastní dashboard
|--------------------------------------------------------------------------
| GA4 má vlastní tabulku, protože nemá útratu a její nákupy jsou jiné číslo
| než nákupy, které si přiřazují reklamní systémy. Sčítat je dohromady by
| konverze započítalo dvakrát.
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_account_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('channel', 100);                    // výchozí seskupení kanálů GA4 (Organic Search, Paid Social…)
            $table->unsignedInteger('sessions')->default(0);
            $table->unsignedInteger('users')->default(0);
            $table->unsignedInteger('engaged_sessions')->default(0);
            $table->decimal('key_events', 12, 2)->default(0);
            $table->decimal('purchases', 12, 2)->default(0);
            $table->decimal('revenue', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['ad_account_id', 'date', 'channel']);
        });

        // Práce navíc nad paušál. Paušál a hodiny v něm jsou u klienta v ad_client_settings.
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('worked_on');
            $table->unsignedInteger('minutes');
            $table->string('description');
            $table->boolean('billable')->default(true);        // nefakturovatelná práce se počítá jen do kapacity
            $table->timestamp('invoiced_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'worked_on']);
        });

        Schema::table('ad_client_settings', function (Blueprint $table) {
            $table->json('dashboard')->nullable()->after('monthly_report'); // které dlaždice a sekce ukázat, null = vše
        });
    }

    public function down(): void
    {
        Schema::table('ad_client_settings', function (Blueprint $table) {
            $table->dropColumn('dashboard');
        });
        Schema::dropIfExists('time_entries');
        Schema::dropIfExists('analytics_daily_stats');
    }
};
