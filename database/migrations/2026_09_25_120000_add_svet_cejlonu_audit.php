<?php

use App\Models\Audit;
use App\Models\Client;
use Illuminate\Database\Migrations\Migration;

/**
 * SEO a GEO audit e-shopu svetcejlonu.cz z 24. 9. 2026, převedený
 * z HTML podkladu do Markdownu. Text leží vedle v database/content/audits/,
 * ať se v migraci dá číst.
 *
 * Stejně jako checklist: založí se jen jednou a další úpravy patří do
 * administrace nástrojů (Checklisty → Audity), ne sem.
 */
return new class extends Migration
{
    private const CLIENT_SLUG = 'svet-cejlonu';

    // Pevný token, ať je odkaz stejný lokálně i na ostrém webu.
    private const PUBLIC_TOKEN = 'kb3mcK5MHcNnlO5IcZzBtc4zkjmyfbQq0Tsz05uT';

    private const OLD_NOTE_PRICE = 'Odhad fáze 1 a 2: 20 400–25 600 Kč bez DPH.';

    public function up(): void
    {
        $client = Client::where('slug', self::CLIENT_SLUG)->first();

        if (! $client || Audit::where('public_token', self::PUBLIC_TOKEN)->exists()) {
            return;
        }

        Audit::create([
            'client_id' => $client->getKey(),
            'title' => 'SEO a GEO audit e-shopu svetcejlonu.cz',
            'audited_at' => '2026-09-24',
            'intro' => 'Prověrka viditelnosti ve vyhledávačích (Google, Seznam, Bing) a v AI asistentech '
                .'(ChatGPT, Perplexity, Gemini, Claude, Copilot). Prošli jsme 1 009 stránek a všech 1 974 adres '
                .'ze sitemapy. Všechno je naměřené na živém webu, protože Google Search Console zatím chybí.',
            'highlights' => [
                ['value' => '1 974', 'label' => 'adres v sitemapě, užitečných je jen asi 80'],
                ['value' => '1 786', 'label' => 'filtračních stránek (22. 9. jich bylo 228)'],
                ['value' => '2 / 14', 'label' => 'AI robotů dostane chybu 503 (GPTBot, ClaudeBot)'],
                ['value' => '0', 'label' => 'vyplněných meta popisků u kategorií a stránek'],
            ],
            'body' => file_get_contents(database_path('content/audits/svet-cejlonu-2026-09-24.md')),
            'public_token' => self::PUBLIC_TOKEN,
            'is_public' => true,
        ]);

        // Poznámka u klienta vznikla s odhadem z první verze auditu. Nabídka
        // se mezitím změnila, tak ji srovnáme, pokud na ni nikdo nesáhl.
        if (str_contains((string) $client->note, self::OLD_NOTE_PRICE)) {
            $client->update(['note' => str_replace(
                self::OLD_NOTE_PRICE,
                'Nabídka: SEO úklid 4 900 Kč + péče 1 900 Kč měsíčně (min. 3 měsíce), bez DPH.',
                $client->note,
            )]);
        }
    }

    public function down(): void
    {
        Audit::where('public_token', self::PUBLIC_TOKEN)->delete();
    }
};
