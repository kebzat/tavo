<?php

use App\Models\Audit;
use App\Models\Client;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Z auditu a checklistu Světa Cejlonu mizí rozdělení „kdo to udělá“
 * (vy / my / administrace). Všechno dělá Tom, klient dodává jen přístupy
 * a podklady, a ty jsou v auditu v „Co od vás budeme potřebovat“.
 *
 * „V administraci: Nastavení → …“ zůstává, říká kde, ne kdo.
 *
 * Audit se přepíše celý, jen když je beze změny od minulé migrace.
 * V checklistu se mění jednotlivé úseky a jen tam, kde je původní znění.
 */
return new class extends Migration
{
    private const AUDIT_TOKEN = 'kb3mcK5MHcNnlO5IcZzBtc4zkjmyfbQq0Tsz05uT';

    /** Otisk textu auditu po migraci 2026_09_25_180000. */
    private const OLD_BODY_SHA1 = '87ad2067c13d8dcb4b0796e2b454478c98f43e72';

    private const CLIENT_SLUG = 'svet-cejlonu';

    /**
     * [tabulka, sloupec, původní úsek, nový úsek]
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private const CHECKLIST = [
        ['checklists', 'intro', 'Pod odkazem „proč“ najdete, co přesně udělat a kde. Hotové si odškrtněte, uvidíme to i my.', 'Pod odkazem „proč“ je u každého úkolu, co přesně se udělá a kde. Hotové úkoly průběžně odškrtáváme, takže tu uvidíte, jak práce postupuje.'],
        ['checklist_sections', 'description', 'Tohle uděláme my v kódu šablony. Od vás potřebujeme jen potvrdit údaje o firmě.', 'Úpravy v kódu šablony. Od vás potřebujeme jen potvrdit údaje o firmě.'],
        ['checklist_sections', 'description', 'Návody píšete vy. Nejsnáz se citují', 'Nejsnáz se citují'],
        ['checklist_items', 'description', 'Uděláme my, po založení Search Console jedním kliknutím.', 'Po založení Search Console se web do Bingu naimportuje jedním kliknutím.'],
        ['checklist_items', 'description', 'Uděláme my. GA4 na webu už běží.', 'GA4 na webu už běží.'],
        ['checklist_items', 'description', 'Uděláme my. Kandidáti jsou', 'Kandidáti jsou'],
        ['checklist_items', 'description', 'Rádce pak naplníme skutečnými návody, viz poslední část checklistu.', 'Místo něj můžou přijít skutečné návody, viz poslední část checklistu.'],
        ['checklist_items', 'description', 'Uděláme my v šabloně. Blok je dnes', 'Úprava v šabloně. Blok je dnes'],
        ['checklist_items', 'description', 'Uděláme my v Seznam Webmasteru, ať stránka o hodinkách zmizí z výsledků co nejdřív.', 'Stránka o hodinkách tak zmizí z výsledků rychleji.'],
        ['checklist_items', 'description', 'Tohle je na vás, text e-mailu vám připravíme. Oba roboti', 'Oba roboti'],
        ['checklist_items', 'description', 'Uděláme my v Nastavení', 'V administraci: Nastavení'],
        ['checklist_items', 'description', 'Uděláme my. Většina stránek', 'Většina stránek'],
        ['checklist_items', 'description', 'Uděláme my. /p/lotovovy-kvet', '/p/lotovovy-kvet'],
        ['checklist_items', 'description', 'Tohle je na vás. Existuje', 'Existuje'],
        ['checklist_items', 'description', 'Tohle je na vás, rádi pomůžeme. Nejdřív ověřit', 'Nejdřív ověřit'],
        ['checklist_items', 'description', 'Vy dodáte fakta, text připravíme.', 'Fakta k tomu potřebujeme od vás.'],
        ['checklist_items', 'description', 'Uděláme my v šabloně. Původ', 'Úprava v šabloně. Původ'],
        ['checklist_items', 'description', 'Uděláme my, fotky jsou připravené.', 'Fotky jsou připravené.'],
        ['checklist_items', 'description', 'Uděláme my. V Search Console projdeme', 'V Search Console projdeme'],
    ];

    public function up(): void
    {
        $audit = Audit::where('public_token', self::AUDIT_TOKEN)->first();

        if ($audit && sha1((string) $audit->body) === self::OLD_BODY_SHA1) {
            $audit->update(['body' => file_get_contents(database_path('content/audits/svet-cejlonu-2026-09-24.md'))]);
        }

        $this->checklist(fn (array $change): array => $change);
    }

    public function down(): void
    {
        // Text auditu zpátky neskládáme, předchozí verze je v historii gitu.
        $this->checklist(fn (array $change): array => [$change[0], $change[1], $change[3], $change[2]]);
    }

    private function checklist(callable $direction): void
    {
        $client = Client::where('slug', self::CLIENT_SLUG)->first();

        if (! $client) {
            return;
        }

        $checklistIds = $client->checklists()->pluck('id');

        foreach (self::CHECKLIST as $change) {
            [$table, $column, $from, $to] = $direction($change);

            $query = match ($table) {
                'checklists' => DB::table($table)->whereIn('id', $checklistIds),
                'checklist_items' => DB::table($table)->whereIn('checklist_id', $checklistIds),
                'checklist_sections' => DB::table($table)->whereIn(
                    'checklist_category_id',
                    DB::table('checklist_categories')->whereIn('checklist_id', $checklistIds)->select('id'),
                ),
            };

            $query->where($column, 'like', '%'.$from.'%')
                ->get(['id', $column])
                ->each(function (object $row) use ($table, $column, $from, $to): void {
                    // Úsek musí být v textu přesně jednou, jinak by náhrada
                    // mohla zasáhnout i to, co už někdo upravil.
                    if (substr_count($row->{$column}, $from) !== 1) {
                        return;
                    }

                    DB::table($table)->where('id', $row->id)->update([
                        $column => str_replace($from, $to, $row->{$column}),
                        'updated_at' => now(),
                    ]);
                });
        }
    }
};
