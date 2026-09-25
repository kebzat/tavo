<?php

use App\Models\Audit;
use Illuminate\Database\Migrations\Migration;

/**
 * Audit Světa Cejlonu s ohledem na to, že klient už běží reklamy na
 * Instagramu a Facebooku: „chybí propojení se sítěmi“ znělo, jako by sítě
 * neměl (jde jen o odkazy ve strukturovaných datech), „Měření“ jako by nic
 * neměřil (GA4 i Meta pixel běží) a pixel jsme řadili mezi to, co brzdí.
 * Pomalé stránky teď mluví o penězích za kliknutí. Pryč je „Jak jsme měřili“.
 *
 * Přepíše jen audit, který od minulé migrace nikdo neupravoval.
 */
return new class extends Migration
{
    private const AUDIT_TOKEN = 'kb3mcK5MHcNnlO5IcZzBtc4zkjmyfbQq0Tsz05uT';

    /** Otisk textu auditu po migraci 2026_09_25_220000. */
    private const OLD_BODY_SHA1 = '73a040687decfc76e6b2a572189d7398889b3686';

    public function up(): void
    {
        $audit = Audit::where('public_token', self::AUDIT_TOKEN)->first();

        if ($audit && sha1((string) $audit->body) === self::OLD_BODY_SHA1) {
            $audit->update(['body' => file_get_contents(database_path('content/audits/svet-cejlonu-2026-09-24.md'))]);
        }
    }

    public function down(): void
    {
        // Předchozí verze je v historii gitu.
    }
};
