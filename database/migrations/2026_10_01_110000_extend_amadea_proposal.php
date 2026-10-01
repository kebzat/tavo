<?php

use App\Models\Proposal;
use Illuminate\Database\Migrations\Migration;

/**
 * Amadea: doplněné body podle Toma (1. 10. 2026). Přeplněné menu, mobil,
 * přetékající box v mobilním košíku, základní SEO a redesign jen jako
 * možnost, ne podmínka. Ověřeno na amadea.cz týž den.
 *
 * Jen přidává: bod se stejným nadpisem, který už na stránce je, přeskočí,
 * a nic nemaže. Úpravy z nástrojů zůstanou.
 *
 * Pravidla pro psaní textů: .claude/skills/tavo-copy/SKILL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        $proposal = Proposal::where('slug', 'amadea')->first();

        if (! $proposal) {
            return;
        }

        $proposal->update([
            'findings' => $this->append($proposal->findings, $this->findings()),
            'recommendations' => $this->insertRecommendations($proposal->recommendations ?? []),
            'steps' => $this->insertSteps($proposal->steps ?? []),
        ]);
    }

    public function down(): void
    {
        // Body mohli mezitím upravit v nástrojích, mažou se tam.
    }

    /** @return list<array<string, mixed>> */
    private function findings(): array
    {
        return [
            [
                'priority' => 'urgent',
                'tone' => 'problem',
                'title' => 'Na mobilu je nákup zbytečně dlouhý a těžký',
                'body' => 'Většina lidí dnes na e-shopy chodí z telefonu, takže mobilní verze je ta, podle které vás posuzují. '
                    .'Na úvodní stránce je první produkt až zhruba po dvou obrazovkách. Menu je dlouhý seznam, ve kterém se hledá těžko. '
                    .'A když stránku projedete celou, telefon stáhne přes 15 MB ve více než 260 souborech, což je na mobilních datech znát. '
                    .'Na mobilu by nákup měl jít nejsnáz ze všeho.',
            ],
            [
                'priority' => 'important',
                'tone' => 'problem',
                'title' => 'V mobilním košíku přetéká box s produktem',
                'body' => 'Po přidání produktu je řádek v košíku na telefonu širší než displej. Pravý okraj boxu je useknutý a součet s křížkem '
                    .'pro odebrání jsou posunuté mimo. Oprava je drobná, jenže košík je místo, kde se o nákupu rozhoduje, '
                    .'a nedotažený vzhled tam ubírá důvěru.',
            ],
            [
                'priority' => 'important',
                'tone' => 'problem',
                'title' => 'Menu je přeplněné',
                'body' => 'V hlavním menu je 13 položek s ikonami a pod nimi přes 70 podkategorií, nad tím ještě horní lišta s dalšími odkazy. '
                    .'Některé se opakují (květináče jsou v Zahradě i v Dekoracích) a v říjnu tam pořád svítí Velikonoce. '
                    .'Kdo neví přesně, co hledá, se v tom ztratí. Chtělo by to menu zestručnit, sloučit, co se opakuje, '
                    .'a sezónní položky řadit podle roční doby.',
            ],
            [
                'priority' => 'important',
                'tone' => 'opportunity',
                'title' => 'Vyhledávače mají o vás méně informací, než by mohly',
                'body' => 'Kategorie Vánoce, která je teď v sezoně, nemá žádný úvodní text a popis pro Google zní jen „Vánoce, Dřevěný obchůdek Amadea.cz“. '
                    .'Úvodní stránka má dva hlavní nadpisy místo jednoho a 128 z 299 obrázků nemá popisek, takže je vyhledávače nepřečtou. '
                    .'Betlémy přitom vlastní text i popis mají, jde tedy hlavně o dotažení zbytku.',
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function recommendations(): array
    {
        return [
            [
                'who' => 'Tom',
                'title' => 'Mobil jako hlavní verze webu',
                'body' => 'Mobil ladíme jako první: kratší cesta k produktům na úvodní stránce, opravený košík, lehčí obrázky '
                    .'a menu, ve kterém se dá rychle zorientovat. Na Shoptetu jde všechno úpravou šablony, bez nového webu.',
            ],
            [
                'who' => 'Tom',
                'title' => 'Zestručnit a uklidit menu',
                'body' => 'Méně hlavních položek, sezónní nahoru (teď Vánoce, na jaře zahrada a Velikonoce) a sloučené podkategorie, '
                    .'které se opakují. Vyjdeme z toho, kam lidé v menu opravdu klikají.',
            ],
            [
                'who' => 'Tom',
                'title' => 'Základní SEO u hlavních kategorií',
                'body' => 'Texty a popisy pro Google u hlavních kategorií, začneme Vánocemi. K tomu jeden hlavní nadpis na stránku '
                    .'a popisky obrázků. Nic složitého, ale bez toho vás vyhledávače najdou hůř.',
            ],
            [
                'who' => 'Tom',
                'title' => 'Redesign jako možnost, ne podmínka',
                'body' => 'Návrh nové úvodní stránky výš ukazuje, kam by se web mohl posunout, a nový vzhled by mu prospěl. '
                    .'Nic z toho, co tu píšeme, na něm ale nestojí. Začneme úpravami a o větší změně se pobavíme, až uvidíme data ze sezony.',
            ],
        ];
    }

    /**
     * Doporučení jsou seřazená podle dopadu na letošní tržby: mobil a menu
     * hned za rychlé opravy, SEO před dlouhodobý rozvoj, redesign na konec.
     *
     * @param  list<array<string, mixed>>  $current
     * @return list<array<string, mixed>>
     */
    private function insertRecommendations(array $current): array
    {
        [$mobile, $menu, $seo, $redesign] = $this->recommendations();
        $titles = array_column($current, 'title');
        $missing = fn (array $item): bool => ! in_array($item['title'], $titles, true);

        array_splice($current, min(1, count($current)), 0, array_values(array_filter([$mobile, $menu], $missing)));

        if ($missing($seo)) {
            $development = array_search('Rozvoj e-shopu podle vašich priorit', array_column($current, 'title'), true);
            array_splice($current, $development === false ? count($current) : $development, 0, [$seo]);
        }

        if ($missing($redesign)) {
            $current[] = $redesign;
        }

        return array_values($current);
    }

    /**
     * Mobil a menu jdou hned za vánoční úvodní stránku, SEO za druhý týden.
     *
     * @param  list<array<string, mixed>>  $steps
     * @return list<array<string, mixed>>
     */
    private function insertSteps(array $steps): array
    {
        $titles = array_column($steps, 'title');
        $mobile = ['when' => '1.–2. týden', 'title' => 'Mobil, košík a menu', 'body' => 'Opravený box v košíku, kratší cesta k produktům na mobilu, lehčí obrázky a zestručněné menu.', 'later' => false];
        $seo = ['when' => '3. týden', 'title' => 'SEO hlavních kategorií', 'body' => 'Texty a popisy pro Google u Vánoc a dalších hlavních kategorií, popisky obrázků, jeden hlavní nadpis.', 'later' => false];

        if (! in_array($mobile['title'], $titles, true)) {
            array_splice($steps, min(1, count($steps)), 0, [$mobile]);
        }

        if (! in_array($seo['title'], $titles, true)) {
            // Před první krok od třetího týdne, jinak za poslední krok prvních týdnů.
            $now = collect($steps)->reject(fn (array $step): bool => (bool) ($step['later'] ?? false));
            $at = $now->search(fn (array $step): bool => str_starts_with((string) ($step['when'] ?? ''), '3'));
            $at = $at === false ? ($now->keys()->last() ?? -1) + 1 : $at;
            array_splice($steps, $at, 0, [$seo]);
        }

        return array_values($steps);
    }

    /**
     * @param  list<array<string, mixed>>|null  $current
     * @param  list<array<string, mixed>>  $new
     * @return list<array<string, mixed>>
     */
    private function append(?array $current, array $new): array
    {
        $current ??= [];
        $titles = array_column($current, 'title');

        foreach ($new as $item) {
            if (! in_array($item['title'], $titles, true)) {
                $current[] = $item;
            }
        }

        return $current;
    }
};
