<?php

use App\Support\ResponsiveImage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * /pro-klienty: dva nové bloky „Karty s ukázkou" před závěrečnou výzvou.
 * Co umíme na e-shopu (počítadlo, dárkové balíčky, cross-selling v košíku,
 * prémiové popisky) a co umíme v marketingu (kampaně, videa, grafika, měření).
 *
 * Jen přidává. Stávající bloky stránky zůstávají, jak jsou, a když už stránka
 * nějaký blok „Karty s ukázkou" má, migrace nedělá nic.
 *
 * Pravidla pro psaní textů: .claude/skills/tavo-copy/SKILL.md
 */
return new class extends Migration
{
    private const SLUG = 'pro-klienty';

    private const TYPE = 'feature_cards';

    /** Soubor v repozitáři → cesta na disku `public` (stejné jako u reference). */
    private const IMAGES = [
        'koruna-pro-skolu.jpg' => 'reference/svet-cejlonu-koruna-pro-skolu.jpg',
        'darkove-balicky.jpg' => 'reference/svet-cejlonu-darkove-balicky.jpg',
    ];

    public function up(): void
    {
        $page = DB::table('pages')->where('slug', self::SLUG)->first();

        if (! $page) {
            return;
        }

        $blocks = json_decode((string) $page->blocks, true) ?: [];

        if (collect($blocks)->contains(fn ($block) => ($block['type'] ?? null) === self::TYPE)) {
            return;
        }

        $this->copyImages();

        // Před poslední výzvu („Co z toho se hodí vám?"), jinak na konec.
        $cta = collect($blocks)->keys()->reverse()->first(fn ($key) => ($blocks[$key]['type'] ?? null) === 'cta');
        array_splice($blocks, $cta === null ? count($blocks) : $cta, 0, [$this->eshopBlock(), $this->marketingBlock()]);

        DB::table('pages')->where('id', $page->id)->update([
            'blocks' => json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function down(): void
    {
        $page = DB::table('pages')->where('slug', self::SLUG)->first();

        if (! $page) {
            return;
        }

        $blocks = json_decode((string) $page->blocks, true) ?: [];
        $kept = array_values(array_filter($blocks, fn ($block) => ($block['type'] ?? null) !== self::TYPE));

        DB::table('pages')->where('id', $page->id)->update([
            'blocks' => json_encode($kept, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /** @return array<string, mixed> */
    private function eshopBlock(): array
    {
        return [
            'type' => self::TYPE,
            'data' => [
                'tone' => 'cream',
                'columns' => 2,
                'eyebrow' => 'Co umíme na e-shopu',
                'title' => 'Funkce, které už u klientů běží',
                'perex' => 'Shoptet, Upgates, Shopify i WooCommerce to řeší každý trochu jinak. Než cokoli slíbíme, podíváme se, na čem váš obchod stojí a co jde postavit bez zbytečného programování.',
                'items' => [
                    $this->card(
                        title: 'Počítadlo do cíle',
                        tag: 'Svět Cejlonu',
                        image: self::IMAGES['koruna-pro-skolu.jpg'],
                        alt: 'Počítadlo na úvodní stránce Světa Cejlonu: kolik korun se vybralo pro školu',
                        what: 'Na webu ukazuje, kolik se už vybralo a kolik chybí do cíle. Částka přibývá sama s každou objednávkou, nikdo ji nepřepočítává v tabulce.',
                        why: 'Zákazník vidí, že jeho nákup k něčemu přispěl. Hodí se na charitu, sbírku k výročí firmy nebo společný cíl se zákazníky.',
                        link: '/reference/svet-cejlonu',
                        linkLabel: 'Jak to běží na Světě Cejlonu',
                    ),
                    $this->card(
                        title: 'Dárkové balíčky',
                        tag: 'Svět Cejlonu',
                        image: self::IMAGES['darkove-balicky.jpg'],
                        alt: 'Konfigurátor dárkového balíčku: krabice s čaji a kořením a seznam položek',
                        what: 'Zákazník si vybere krabici a skládá do ní produkty. Web hlídá, kolik se do ní vejde, a do košíku pošle celý balíček najednou i s krabicí.',
                        why: 'Z několika drobností je dárek s vyšší hodnotou objednávky. Kdo nechce skládat od nuly, vezme hotový balíček a jen ho upraví.',
                        link: '/reference/svet-cejlonu',
                        linkLabel: 'Ukázka v referenci',
                    ),
                    $this->card(
                        title: 'Cross-selling v košíku',
                        tag: 'Košík',
                        what: 'V košíku nabídne produkty, které se hodí k tomu, co v něm už je. Náplň k přístroji, louhovač k sypanému čaji. Do objednávky se přidají jedním kliknutím, bez návratu do e-shopu.',
                        why: 'V košíku je zákazník už rozhodnutý nakoupit. Doplněk za pár desítek korun tam přidá snáz než na detailu produktu, kde ještě vybírá.',
                    ),
                    $this->card(
                        title: 'Prémiové popisky produktů',
                        tag: 'Detail produktu',
                        what: 'Místo jedné věty a tabulky parametrů popis složený z bloků: fotky z použití, ikony hlavních výhod, postup, složení a časté otázky. Šablonu připravíme jednou, pak se do ní jen doplňuje text.',
                        why: 'Popis od dodavatele má i konkurence. Vlastní popis odpoví na otázky, kvůli kterým by zákazník jinak psal na podporu, nebo odešel jinam.',
                    ),
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function marketingBlock(): array
    {
        return [
            'type' => self::TYPE,
            'data' => [
                'tone' => 'ink',
                'columns' => 2,
                'eyebrow' => 'Co umíme v marketingu',
                'title' => 'Kampaně, videa a grafika do reklam',
                'perex' => 'Kampaně vede Pavel, výkonnostní reklamě se věnuje devět let, hlavně na Facebooku a Instagramu. Na natáčení a focení zapojujeme ověřené partnery a předem řekneme, kdo co udělá a za kolik.',
                'items' => [
                    $this->card(
                        title: 'Kampaně na Facebooku a Instagramu',
                        tag: 'Meta',
                        what: 'Nastavíme reklamní účet, měření a kampaně od první návštěvy po nákup. Pak je každý týden ladíme podle čísel z účtu a z webu.',
                        why: 'Rozpočet přesouváme do reklam, které přivádějí nákupy a poptávky. Co jen sbírá lajky, vypínáme.',
                        link: '/reference/sprava-reklam-svet-cejlonu',
                        linkLabel: 'Správa reklam pro Svět Cejlonu',
                    ),
                    $this->card(
                        title: 'Reels a krátká videa',
                        tag: 'Video',
                        what: 'Napíšeme scénář, natočíme telefonem, nebo s kameramanem, když je potřeba víc. Sestříháme verze pro reklamu i pro profil.',
                        why: 'Video ukáže produkt v ruce a lidi za firmou. To se fotkou z katalogu udělat nedá.',
                    ),
                    $this->card(
                        title: 'Grafika do reklam',
                        tag: 'Grafika',
                        what: 'Bannery, karusely a několik verzí jedné reklamy ve formátech pro feed, stories i reels.',
                        why: 'Víc verzí vedle sebe rychle ukáže, který obrázek a který text lidi zastaví. Další měsíc pak stavíme na tom, co fungovalo.',
                    ),
                    $this->card(
                        title: 'Měření a měsíční report',
                        tag: 'Data',
                        what: 'Zkontrolujeme Meta Pixel, Google Analytics a konverze na webu. Každý měsíc dostanete report s čísly a s komentářem, co jsme udělali a co chystáme dál.',
                        why: 'Bez funkčního měření se reklama ladí naslepo a nikdo neví, co vlastně vydělává.',
                    ),
                ],
            ],
        ];
    }

    /** @return array<string, ?string> */
    private function card(
        string $title,
        string $tag,
        string $what,
        string $why,
        ?string $image = null,
        ?string $alt = null,
        ?string $link = null,
        ?string $linkLabel = null,
    ): array {
        return [
            'title' => $title,
            'tag' => $tag,
            'image' => $image,
            'image_alt' => $alt,
            'what' => $what,
            'why' => $why,
            'link_url' => $link,
            'link_label' => $linkLabel,
        ];
    }

    /** Obrázky nahrála už migrace reference Svět Cejlonu, tady jen pojistka. */
    private function copyImages(): void
    {
        foreach (self::IMAGES as $source => $target) {
            $path = database_path("seeders/assets/svet-cejlonu/{$source}");

            if (! Storage::disk('public')->exists($target) && File::isFile($path)) {
                Storage::disk('public')->put($target, File::get($path));
                ResponsiveImage::generate($target);
            }
        }
    }
};
