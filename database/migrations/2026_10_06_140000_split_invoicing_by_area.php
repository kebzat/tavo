<?php

/*
|--------------------------------------------------------------------------
| Fakturace po oblastech: vývoj fakturuje Tom, marketing Pavel
|--------------------------------------------------------------------------
| Zatím fakturuje každý sám za sebe, takže jeden klient může mít za měsíc
| dvě faktury. client_invoices proto dostává oblast a „vyfakturováno“ platí
| pro dvojici klient + oblast.
|
| users.billing_area říká, kdo kterou oblast fakturuje (administrace →
| Uživatelé). Až se bude fakturovat společně, stačí ji u obou vyprázdnit.
|
| crm_deals.area přepíše oblast, kterou jinak zakázka dostane podle balíčku
| (App\Enums\Crm\DealPackage::area()).
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_invoices', function (Blueprint $table) {
            $table->string('area', 20)->nullable()->after('month');
            $table->unique(['client_id', 'month', 'area']);
        });

        Schema::table('client_invoices', function (Blueprint $table) {
            $table->dropUnique(['client_id', 'month']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('billing_area', 20)->nullable()->after('is_reviewer');
        });

        DB::table('users')->where('email', 'tom@taveo.cz')->update(['billing_area' => 'web']);
        DB::table('users')->where('email', 'pavel@taveo.cz')->update(['billing_area' => 'marketing']);

        Schema::table('crm_deals', function (Blueprint $table) {
            $table->string('area', 20)->nullable()->after('package');
        });
    }

    public function down(): void
    {
        Schema::table('crm_deals', function (Blueprint $table) {
            $table->dropColumn('area');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('billing_area');
        });

        Schema::table('client_invoices', function (Blueprint $table) {
            $table->unique(['client_id', 'month']);
        });

        Schema::table('client_invoices', function (Blueprint $table) {
            $table->dropUnique(['client_id', 'month', 'area']);
            $table->dropColumn('area');
        });
    }
};
