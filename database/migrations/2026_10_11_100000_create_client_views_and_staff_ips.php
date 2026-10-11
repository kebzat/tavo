<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Otevření sdílených dokumentů po jednom, s IP adresou
|--------------------------------------------------------------------------
|
| Dosud se jen přičítalo do view_count, takže se nedalo poznat, jestli
| dokument otevřel klient, nebo my bez přihlášení (z mobilu, v jiném
| prohlížeči). Teď se každé otevření zapíše zvlášť a počty se z nich
| dopočítají bez našich adres.
|
| staff_ips: adresy, ze kterých jsme byli přihlášení. Síť je IPv4 adresa,
| nebo u IPv6 prefix /64 (domácí a firemní připojení mění konec adresy).
|
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_views', function (Blueprint $table) {
            $table->id();
            $table->morphs('viewable');
            $table->string('ip', 45)->nullable();
            $table->string('network', 64)->nullable()->index();
            $table->string('user_agent', 500)->nullable();
            $table->dateTime('viewed_at');
        });

        Schema::create('staff_ips', function (Blueprint $table) {
            $table->id();
            $table->string('network', 64)->unique();
            $table->string('ip', 45);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('first_seen_at');
            $table->dateTime('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_ips');
        Schema::dropIfExists('client_views');
    }
};
