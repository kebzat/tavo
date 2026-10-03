<?php

use App\Support\ClientDashboardDemo;
use Illuminate\Database\Migrations\Migration;

/**
 * Ukázkový přehled spolupráce s vymyšleným e-shopem, ať jde poslat odkaz
 * a ukázat, jak přehled vypadá. Odkaz je u klienta „Ukázka: Bylinky
 * z Podkrkonoší“ v nástrojích. Data se obnovují 1. v měsíci.
 *
 * Založí se jen jednou. Smazaná ukázka se už nevrátí.
 */
return new class extends Migration
{
    public function up(): void
    {
        $demo = app(ClientDashboardDemo::class);

        if (! $demo->client()) {
            $demo->install();
        }
    }
};
