<?php

/*
|--------------------------------------------------------------------------
| Audit jako obchodní nástroj
|--------------------------------------------------------------------------
| Z karty firmy v CRM vznikne audit s checklistem. Audit i checklist visí
| na klientovi, takže klient dostane vazbu na firmu v CRM a audit dvě věci
| navíc:
|
| - omezený režim: klient vidí shrnutí a první nález, zbytek je zamčený
|   s výzvou k hovoru,
| - počítadlo otevření: první otevření se zapíše do CRM jako aktivita
|   s follow-upem, ať víme, kdy zavolat.
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('crm_company_id')->nullable()->after('id')
                ->constrained('crm_companies')->nullOnDelete();
        });

        Schema::table('audits', function (Blueprint $table) {
            $table->boolean('is_teaser')->default(false)->after('is_public');
            $table->unsignedInteger('view_count')->default(0)->after('is_teaser');
            $table->dateTime('first_viewed_at')->nullable()->after('view_count');
            $table->dateTime('last_viewed_at')->nullable()->after('first_viewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropColumn(['is_teaser', 'view_count', 'first_viewed_at', 'last_viewed_at']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('crm_company_id');
        });
    }
};
