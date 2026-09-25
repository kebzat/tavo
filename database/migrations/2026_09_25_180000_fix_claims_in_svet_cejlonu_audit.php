<?php

use App\Models\Audit;
use App\Models\Client;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Opravy v auditu a checklistu Světa Cejlonu po kontrole proti datům z měření.
 *
 * - Tvrzení silnější, než jsme změřili: ChatGPT a Claude e-shop najdou přes
 *   vyhledávání, blokovaní jsou jen trénovací roboti. Dotazy šly přes webové
 *   vyhledávání, ne přímo do AI. Hvězdičky ani Business Profile bez Search
 *   Console neověříme. Který vyhledávač ukazuje variantu, nevíme.
 * - Chyby: „24. 9. 2026“ na začátku řádku dělalo z data číslovaný seznam,
 *   ibišek se prodává po 20–80 g, vzorový titulek měl 61 znaků.
 * - Články nepíšeme: z nabídky pryč, v auditu i checklistu jen jako práce klienta.
 * - Měsíční spolupráce rozepsaná po měsících a co bude po třetím.
 *
 * Audit se přepíše celý, jen když je beze změny od minulé migrace. V checklistu
 * se mění jednotlivé věty a jen tam, kde je původní znění.
 */
return new class extends Migration
{
    private const AUDIT_TOKEN = 'kb3mcK5MHcNnlO5IcZzBtc4zkjmyfbQq0Tsz05uT';

    /** Otisk textu auditu po migraci 2026_09_25_170000. */
    private const OLD_BODY_SHA1 = 'f80d56f968934c886e711b35952aa4a3476d340e';

    private const CLIENT_SLUG = 'svet-cejlonu';

    /**
     * [tabulka, sloupec, původní úsek, nový úsek]
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private const CHECKLIST = [
        ['checklist_items', 'title', 'Založit Google Business Profile', 'Ověřit nebo založit Google Business Profile'],
        ['checklist_items', 'description', 'Tohle je na vás, rádi pomůžeme. Jako firma bez provozovny', 'Tohle je na vás, rádi pomůžeme. Nejdřív ověřit, jestli profil už neexistuje. Pokud ne, založit jako firmu bez provozovny'],
        ['checklist_items', 'description', 'Bing už takovou variantu ukazuje místo hlavního produktu.', 'Ve výsledcích vyhledávání se už taková varianta objevuje místo hlavního produktu.'],
        ['checklist_items', 'description', 'Ibišek má 25 recenzí s průměrem 5,0 a ve výsledcích hledání to není vidět.', 'Ibišek má 25 recenzí s průměrem 5,0, ale hvězdičky se ve výsledcích nejspíš nezobrazí.'],
        ['checklist_items', 'description', 'Vzor: „Ibiškový čaj – sušený květ ibišku ze Srí Lanky | Svět Cejlonu“.', 'Vzor: „Ibiškový čaj – sušený květ ze Srí Lanky | Svět Cejlonu“.'],
        ['checklist_items', 'description', 'odkazy na skořici celou i mletou. Tohle AI dnes cituje od konkurence.', 'odkazy na skořici celou i mletou.'],
        ['checklist_items', 'title', 'Další články podle seznamu z auditu', 'Další návody'],
        ['checklist_sections', 'description', 'ChatGPT a Claude se tak o e-shopu nemají odkud dozvědět.', 'Modely se tak z e-shopu nic nenaučí a najdou ho jen přes vyhledávání.'],
        ['checklist_sections', 'title', 'Rádce: články, které AI cituje', 'Rádce: návody, které můžete napsat'],
        ['checklist_sections', 'description', 'Na dotaz „kde koupit pravou cejlonskou skořici“ dnes AI doporučí cejlonskekoreni.cz. Nemají lepší zboží, jen napsali, kolik kumarinu jejich skořice obsahuje.', 'Návody píšete vy. Nejsnáz se citují konkrétní čísla a vysvětlení: na dotaz „kde koupit pravou cejlonskou skořici“ se dnes ve výsledcích objevuje cejlonskekoreni.cz s větou o obsahu kumarinu.'],
        ['checklist_categories', 'description', 'Na obecné dotazy dnes AI doporučuje konkurenci, tady se to dá změnit.', 'Na obecné dotazy se dnes ve výsledcích objevuje konkurence.'],
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
                'checklist_items' => DB::table($table)->whereIn('checklist_id', $checklistIds),
                'checklist_sections' => DB::table($table)->whereIn(
                    'checklist_category_id',
                    DB::table('checklist_categories')->whereIn('checklist_id', $checklistIds)->select('id'),
                ),
                'checklist_categories' => DB::table($table)->whereIn('checklist_id', $checklistIds),
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
