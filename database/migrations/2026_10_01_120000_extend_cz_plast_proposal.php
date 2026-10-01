<?php

use App\Models\Proposal;
use Illuminate\Database\Migrations\Migration;

/**
 * CZ Plast: cookie lišta a měření na webu i e-shopu, plus to, že e-shop
 * umíme upravovat stejně jako web. Ověřeno 1. 10. 2026 na czplast.cz
 * a eshop.czplast.cz v čistém prohlížeči bez kliknutí do lišty: na e-shopu
 * se před souhlasem spustí dva Meta Pixely a retargeting Seznamu, Google
 * čeká na souhlas (Consent Mode, výchozí stav denied).
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
        $proposal = Proposal::where('slug', 'cz-plast')->first();

        if (! $proposal) {
            return;
        }

        [$cookies, $eshop] = $this->recommendations();

        // Cookies hned za rychlé opravy, e-shop za propojení webu a e-shopu (jinak na konec).
        $recommendations = $this->insertAfter($proposal->recommendations ?? [], 0, $cookies);
        $recommendations = $this->insertAfter($recommendations, $this->indexOf($recommendations, 'Propojit web a e-shop'), $eshop);

        $proposal->update([
            'findings' => $this->append($proposal->findings ?? [], $this->findings()),
            'recommendations' => $recommendations,
            'steps' => $this->insertAfter($proposal->steps ?? [], 0, [
                'when' => '1. týden',
                'title' => 'Cookie lišta a měření na webu i e-shopu',
                'body' => 'Tlačítko pro odmítnutí vedle souhlasu, Facebook a Seznam až po souhlasu, opravený skript Seznamu.',
                'later' => false,
            ]),
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
                'title' => 'E-shop posílá data Facebooku a Seznamu ještě před souhlasem',
                'body' => 'Na eshop.czplast.cz se hned při načtení, bez jakéhokoli kliknutí v cookie liště, spustí Meta Pixel, '
                    .'rovnou dva různé, a retargeting Seznamu. Oba si do prohlížeče uloží své cookies. Podle českého zákona '
                    .'o elektronických komunikacích a pravidel EU smí reklamní cookies vzniknout až po souhlasu. '
                    .'Google Analytics a Google Ads přitom máte nastavené správně, čekají, až návštěvník souhlasí. '
                    .'Facebook a Seznam stačí napojit stejně (ověřeno 1. 10. 2026).',
            ],
            [
                'priority' => 'important',
                'tone' => 'problem',
                'title' => 'Souhlasit jde jedním kliknutím, odmítnout až v nastavení',
                'body' => 'Na webu i e-shopu má cookie lišta výrazné tlačítko Souhlasit a zavřít a vedle jen odkaz Informace a nastavení. '
                    .'Možnost Pouze nezbytné cookies se objeví až po rozkliknutí. Úřad pro ochranu osobních údajů i evropští regulátoři chtějí, '
                    .'aby odmítnutí bylo stejně snadné jako souhlas: dvě rovnocenná tlačítka hned v liště. '
                    .'Text lišty je navíc dlouhý a obecný, na mobilu zabere půl obrazovky.',
            ],
            [
                'priority' => 'important',
                'tone' => 'problem',
                'title' => 'Měření Seznamu na hlavním webu nefunguje',
                'body' => 'V kódu retargetingu Seznamu na czplast.cz chybí ID účtu a souhlas je v něm nastavený natvrdo. '
                    .'Prohlížeč na tom řádku hlásí chybu, takže Seznam z hlavního webu nejspíš nedostává nic. '
                    .'Vlastní měřicí skript webu zase ukládá cookie na rok ještě před souhlasem.',
            ],
        ];
    }

    /** @return array{0: array<string, string>, 1: array<string, string>} */
    private function recommendations(): array
    {
        return [
            [
                'who' => 'Tom',
                'title' => 'Cookie lišta a měření podle pravidel',
                'body' => 'Lišta se dvěma rovnocennými tlačítky, Meta Pixel a Seznam spuštěné až po souhlasu, stejně jako už to funguje u Googlu. '
                    .'K tomu jeden pixel místo dvou a opravený skript Seznamu. Nejsme právníci, technickou stránku ale umíme nastavit tak, '
                    .'aby odpovídala pravidlům a data z reklam byla čistá. Na webu i e-shopu.',
            ],
            [
                'who' => 'Tom',
                'title' => 'Web i e-shop z jedné ruky',
                'body' => 'E-shop na eshop.czplast.cz umíme upravovat stejně jako hlavní web: rychlost, formuláře, cookie lištu, vzhled '
                    .'i napojení na hlavní web. Úpravy pak děláme na obou místech najednou, ať zákazník mezi nimi přechází '
                    .'bez rozdílu ve vzhledu a ovládání.',
            ],
        ];
    }

    /** @param  list<array<string, mixed>>  $items */
    private function indexOf(array $items, string $title): ?int
    {
        $index = array_search($title, array_column($items, 'title'), true);

        return $index === false ? null : $index;
    }

    /**
     * Vloží položku za danou pozici (null = na konec). Když tam položka se
     * stejným nadpisem už je, nechá seznam být.
     *
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $item
     * @return list<array<string, mixed>>
     */
    private function insertAfter(array $items, ?int $after, array $item): array
    {
        if ($this->indexOf($items, $item['title']) !== null) {
            return $items;
        }

        // Pozice se počítá v původním seznamu, vložení před ní ji posune o jednu.
        $at = $after === null ? count($items) : min($after + 1, count($items));
        array_splice($items, $at, 0, [$item]);

        return array_values($items);
    }

    /**
     * @param  list<array<string, mixed>>  $current
     * @param  list<array<string, mixed>>  $new
     * @return list<array<string, mixed>>
     */
    private function append(array $current, array $new): array
    {
        foreach ($new as $item) {
            if ($this->indexOf($current, $item['title']) === null) {
                $current[] = $item;
            }
        }

        return $current;
    }
};
