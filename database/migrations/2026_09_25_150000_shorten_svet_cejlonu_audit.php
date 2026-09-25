<?php

use App\Models\Audit;
use Illuminate\Database\Migrations\Migration;

/**
 * Zkrácený audit Světa Cejlonu. Z 8 700 slov zbyla asi třetina: pryč je
 * příloha s 59 produkty, ukázky kódu, kapitola o Search Console a všechno,
 * co se opakovalo v checklistu. Nová verze leží v database/content/audits/.
 *
 * Přepíše jen audit, který od minulé migrace nikdo neupravoval (kontrola
 * přes otisk textu). Úpravy z administrace zůstanou.
 */
return new class extends Migration
{
    private const PUBLIC_TOKEN = 'kb3mcK5MHcNnlO5IcZzBtc4zkjmyfbQq0Tsz05uT';

    /** Otisk textu po migraci 2026_09_25_140000. */
    private const OLD_BODY_SHA1 = '83756f874aab3e6bf23303470750f7fca9a85cb6';

    private const OLD_INTRO = 'Prověrka viditelnosti ve vyhledávačích (Google, Seznam, Bing) a v AI asistentech (ChatGPT, Perplexity, Gemini, Claude, Copilot). Prošli jsme 1 009 stránek a všech 1 974 adres ze sitemapy. Všechno je naměřené na živém webu, protože Google Search Console zatím chybí.';

    private const NEW_INTRO = 'Jak e-shop vidí Google, Seznam a AI asistenti jako ChatGPT nebo Perplexity, a co opravit nejdřív.';

    public function up(): void
    {
        $audit = Audit::where('public_token', self::PUBLIC_TOKEN)->first();

        if (! $audit) {
            return;
        }

        if (sha1((string) $audit->body) === self::OLD_BODY_SHA1) {
            $audit->body = file_get_contents(database_path('content/audits/svet-cejlonu-2026-09-24.md'));
        }

        if ($audit->intro === self::OLD_INTRO) {
            $audit->intro = self::NEW_INTRO;
        }

        $audit->save();
    }

    public function down(): void
    {
        // Dlouhou verzi zpátky neskládáme, zůstala v historii gitu.
    }
};
