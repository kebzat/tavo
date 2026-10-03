<?php

use App\Enums\WorkArea;
use App\Models\Client;
use App\Models\Crm\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Česká louka (microgreens, Hradec Králové): pravidelná spolupráce od října
 * 2026, vývoj webu za 10 000 Kč a marketing za 5 000 Kč měsíčně.
 *
 * Kolik hodin je v paušálu, zatím nevíme. Pole zůstává prázdné, přehled pak
 * hodiny jen ukazuje a nad rámec se nic neúčtuje. Doplní se v administraci.
 * Přehled pro klienta je vypnutý, dokud v něm nebudou první úkoly.
 *
 * Založí se jen jednou. Další úpravy patří do administrace, ne sem.
 */
return new class extends Migration
{
    private const SLUG = 'ceska-louka';

    public function up(): void
    {
        if (Client::where('slug', self::SLUG)->exists()) {
            return;
        }

        DB::transaction(function (): void {
            $client = Client::create([
                'name' => 'Česká louka',
                'slug' => self::SLUG,
                'website_url' => 'https://www.ceskalouka.cz',
                'crm_company_id' => Company::query()->where('website', 'like', '%ceskalouka.cz%')->value('id'),
                'started_on' => '2026-10-01',
                'dashboard_token' => Str::random(40),
                'dashboard_enabled' => false,
            ]);

            $client->retainers()->createMany([
                ['area' => WorkArea::Web, 'label' => 'Vývoj webu', 'monthly_fee' => 10000, 'starts_on' => '2026-10-01', 'order_column' => 1],
                ['area' => WorkArea::Marketing, 'label' => 'Marketing', 'monthly_fee' => 5000, 'starts_on' => '2026-10-01', 'order_column' => 2],
            ]);
        });
    }
};
