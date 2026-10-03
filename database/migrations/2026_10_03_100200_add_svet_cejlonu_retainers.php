<?php

use App\Enums\WorkArea;
use App\Models\Client;
use Illuminate\Database\Migrations\Migration;

/**
 * Svět Cejlonu platí měsíčně 5 000 Kč za vývoj webu a 5 000 Kč za marketing.
 *
 * Začátek spolupráce ani hodiny v paušálu zatím nevíme, doplní se
 * v administraci. Přehled pro klienta zůstává vypnutý.
 *
 * Založí se jen jednou. Další úpravy patří do administrace, ne sem.
 */
return new class extends Migration
{
    public function up(): void
    {
        $client = Client::where('slug', 'svet-cejlonu')->first();

        if (! $client || $client->retainers()->exists()) {
            return;
        }

        $client->retainers()->createMany([
            ['area' => WorkArea::Web, 'label' => 'Vývoj webu', 'monthly_fee' => 5000, 'order_column' => 1],
            ['area' => WorkArea::Marketing, 'label' => 'Marketing', 'monthly_fee' => 5000, 'order_column' => 2],
        ]);
    }
};
