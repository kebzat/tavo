<?php

/*
|--------------------------------------------------------------------------
| Fakturace: co už je za měsíc vyfakturované
|--------------------------------------------------------------------------
| Dosud šly označit jen zapsané hodiny (time_entries.invoiced_at). Klient,
| který platí jen paušál a hodiny se mu nezapisují, tak neměl jak dostat
| „vyfakturováno“. Řádek tady znamená: za tenhle měsíc je faktura venku,
| na tuhle částku.
|
| Jednorázové vyhrané obchody (migrace, nový web) si stav nesou samy
| ve crm_deals.invoiced_at.
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->date('month');
            $table->unsignedInteger('amount_czk');
            $table->dateTime('invoiced_at');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['client_id', 'month']);
        });

        Schema::table('crm_deals', function (Blueprint $table) {
            $table->dateTime('invoiced_at')->nullable()->after('lost_reason');
        });
    }

    public function down(): void
    {
        Schema::table('crm_deals', function (Blueprint $table) {
            $table->dropColumn('invoiced_at');
        });

        Schema::dropIfExists('client_invoices');
    }
};
