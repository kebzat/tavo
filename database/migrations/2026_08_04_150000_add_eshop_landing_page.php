<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Dopadová stránka `/e-shop` pro oslovování majitelů e-shopů.
 *
 * Obsah stránek bydlí v databázi, ale tuhle jednu potřebujeme dostat na produkci
 * bez seederu (ten se pouští jen při prvním nasazení). Nasazení spouští `migrate`,
 * takže ji sem přinese tahle migrace.
 *
 * Chová se jako `add()` u settings migrací: **stránku založí, jen když ještě
 * neexistuje.** Když ji správce mezitím upravil v administraci, migrace se jí
 * nedotkne. Přeformulovat texty po spuštění patří do administrace, ne sem.
 *
 * Obrázky nejdou přes git ve `storage/` (ta je ignorovaná), leží proto
 * v `database/seeders/assets/` a migrace je z repozitáře zkopíruje na disk.
 */
return new class extends Migration
{
    private const SLUG = 'e-shop';

    /** Soubor v repozitáři → cesta na disku `public`. */
    private const IMAGES = [
        'e-shop/2e-pred.jpg' => 'pages/2e-pred.jpg',
        'e-shop/2e-po.jpg' => 'pages/2e-po.jpg',
        'pavel-tom.png' => 'pages/pavel-tom.png',
    ];

    public function up(): void
    {
        if (DB::table('pages')->where('slug', self::SLUG)->exists()) {
            return;
        }

        $this->copyImages();

        DB::table('pages')->insert([
            'slug' => self::SLUG,
            'title' => 'Předěláme e-shop, který nevydělává',
            'hero_eyebrow' => 'Pro majitele e-shopů',
            'hero_accent' => 'nevydělává',
            'hero_cta' => true,
            'perex' => 'Podíváme se na váš e-shop a napíšeme, co ho brzdí. Někdy stačí opravit pár věcí, jindy vyjde levněji postavit ho znovu. Shoptet, Upgates, Shopify, WooCommerce i vlastní řešení, na platformě nám nezáleží.',
            // Bez „| Taveo", příponu titulku připojuje Nastavení → SEO.
            'seo_title' => 'Předěláme e-shop, který nevydělává',
            'seo_description' => 'Projdeme váš e-shop na Shoptetu, Upgates, Shopify nebo WooCommerce a napíšeme, co ho brzdí. Převod, optimalizace, nebo nový e-shop. Odpověď do dvou dnů.',
            'published' => true,
            'blocks' => json_encode($this->blocks(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('pages')->where('slug', self::SLUG)->delete();

        // Obrázky necháváme být. Správce mohl mezitím nahrát vlastní a mazat mu
        // soubory kvůli rollbacku migrace by bylo víc škody než užitku.
    }

    /**
     * Zkopíruje obrázky z repozitáře na disk `public`. Existující soubor
     * nepřepisuje, aby se při opakovaném běhu nepřemazalo, co tam dal správce.
     */
    private function copyImages(): void
    {
        foreach (self::IMAGES as $source => $target) {
            $path = database_path("seeders/assets/{$source}");

            if (! File::isFile($path) || Storage::disk('public')->exists($target)) {
                continue;
            }

            Storage::disk('public')->put($target, File::get($path));
        }
    }

    /**
     * Devět sekcí, ve kterých se pravidelně střídá světlé a tmavé pozadí, aby
     * nikde nevznikly dva tmavé pruhy za sebou.
     *
     * Ceny a čísla z odvedené práce tu schválně nejsou: nemáme je od klientů
     * potvrzené. Doplňte je v administraci, až je mít budete.
     *
     * @return array<int, array{type: string, data: array<string, mixed>}>
     */
    private function blocks(): array
    {
        return [
            [
                'type' => 'points',
                'data' => [
                    'tone' => 'ink',
                    'eyebrow' => 'Co hledáme',
                    'title' => 'Podle čeho poznáme e-shop, který nevydělává',
                    'perex' => 'Než něco nabídneme, projdeme si váš e-shop jako zákazník, který u vás chce nakoupit. Většina problémů je vidět za deset minut.',
                    'items' => [
                        'Na mobilu se první obrázek načítá pět vteřin a zákazník je mezitím pryč.',
                        'Košík chce registraci.',
                        'Fotky produktů jsou od dodavatele, takže je má i konkurence.',
                        'V popisu je jedna věta a tabulka parametrů.',
                        'Cenu dopravy se zákazník dozví až v posledním kroku objednávky.',
                        'Měření běží, ale nikdo se do něj rok nepodíval.',
                    ],
                ],
            ],
            [
                'type' => 'pills',
                'data' => [
                    'tone' => 'cream',
                    'eyebrow' => 'Technologie',
                    'title' => 'Na čem e-shop běží, řešíme až podle vás',
                    'perex' => 'Platformu vybíráme podle toho, kolik máte produktů, kdo bude obsah spravovat a jestli potřebujete napojení na sklad nebo účetnictví. Když e-shop běží na vlastním řešení od někoho, kdo už nekomunikuje, dá se to pořád vyřešit. Jen to trvá dýl.',
                    'items' => [
                        'Shoptet',
                        'Upgates',
                        'Shopify',
                        'WooCommerce',
                        'PrestaShop',
                        'Laravel',
                        'vlastní řešení',
                    ],
                ],
            ],
            [
                'type' => 'before_after',
                'data' => [
                    'tone' => 'ink',
                    'eyebrow' => 'Jak to vypadá po nás',
                    'title' => 'Přetáhněte čáru a uvidíte rozdíl',
                    'perex' => 'E-shop s kompresory 2e. Vlevo stav, ve kterém k nám přišel, vpravo ten dnešní. Zákazník vidí kategorie hned na úvodní stránce a nemusí luštit odstavec o sortimentu.',
                    'before' => 'pages/2e-pred.jpg',
                    'after' => 'pages/2e-po.jpg',
                    'before_alt' => 'Původní úvodní stránka e-shopu 2e Kompresory',
                    'after_alt' => 'Nová úvodní stránka e-shopu 2e Kompresory s dlaždicemi kategorií',
                    'before_label' => 'Před',
                    'after_label' => 'Po',
                ],
            ],
            [
                'type' => 'cards',
                'data' => [
                    'title' => 'Tři cesty. Kterou zvolíme, poznáme až z vašeho e-shopu.',
                    'columns' => 3,
                    'items' => [
                        [
                            'title' => 'Optimalizace',
                            'text' => 'Základ je v pořádku a chybí detaily. Rychlost, mobil, průchod košíkem, popisy produktů. Nejlevnější varianta a do měsíce víte, jestli zabrala.',
                        ],
                        [
                            'title' => 'Převod na jinou platformu',
                            'text' => 'Současné řešení vás brzdí nebo za něj platíte víc, než dává smysl. Přeneseme produkty, objednávky i zákazníky a staré adresy necháme fungovat, aby e-shop nespadl ve vyhledávání.',
                        ],
                        [
                            'title' => 'Nový e-shop',
                            'text' => 'Stávající je tak starý, že se v něm každá úprava dělá dvakrát. Postavíme ho znovu a přeneseme, co má cenu přenášet.',
                        ],
                    ],
                ],
            ],
            [
                'type' => 'steps',
                'data' => [
                    'tone' => 'ink',
                    'eyebrow' => 'Postup',
                    'title' => 'Co se stane, když nám napíšete',
                    'items' => [
                        [
                            'title' => 'Projdeme e-shop',
                            'text' => 'Jako zákazník, na mobilu i na počítači. Zkusíme dokončit objednávku.',
                        ],
                        [
                            'title' => 'Pošleme nález',
                            'text' => 'Do dvou pracovních dnů. Konkrétní věci s odkazy na místa, kde to drhne.',
                        ],
                        [
                            'title' => 'Doporučíme cestu',
                            'text' => 'Napíšeme, která ze tří variant vám dává smysl a proč zrovna ta.',
                        ],
                        [
                            'title' => 'Cena a termín',
                            'text' => 'Když zjistíme, že vám předělání e-shopu nepomůže, řekneme to. Stává se to.',
                        ],
                    ],
                ],
            ],
            [
                'type' => 'metrics',
                'data' => [
                    'tone' => 'cream',
                    'title' => 'Co vám slibujeme',
                    'items' => [
                        ['value' => 'do 2 dnů', 'label' => 'odpověď na e-shop, který nám pošlete'],
                        ['value' => '0 Kč', 'label' => 'za projití e-shopu a doporučení, co s ním'],
                        ['value' => '2 lidi', 'label' => 'Pavel a Tom, žádná agentura mezi tím'],
                    ],
                    'note' => 'Čísla o výsledcích konkrétních projektů tu schválně nejsou. Uvádíme jen to, co máme od klientů potvrzené, a zatím to necháváme na osobní domluvě.',
                ],
            ],
            [
                'type' => 'image_text',
                'data' => [
                    'tone' => 'ink',
                    'side' => 'left',
                    'image' => 'pages/pavel-tom.png',
                    'image_alt' => 'Pavel Včeliš a Tom Kebza',
                    'eyebrow' => 'Kdo to dělá',
                    'title' => 'Dva lidé z Hradce Králové',
                    'body' => '<p>Pavel Včeliš dělá výkonnostní reklamu osmým rokem, hlavně Meta a e-shopy. Tom Kebza staví weby a e-shopy. Na projektu jsme oba, takže se nestane, že by reklama slibovala něco, co e-shop neumí.</p>'
                        .'<p>Víc o nás na <a href="/#lide">úvodní stránce</a>.</p>',
                ],
            ],
            [
                'type' => 'text',
                'data' => [
                    'body' => '<h2>Co to stojí</h2>'
                        .'<p>Cenu pošleme, až si e-shop projdeme. Bez toho by to bylo číslo z ničeho. Nejlevněji vychází optimalizace, u převodu záleží hlavně na tom, kolik dat se stěhuje, nový e-shop je nejdražší.</p>'
                        .'<p>Když se během práce objeví něco, s čím jsme nepočítali, ozveme se dřív, než to uděláme.</p>',
                ],
            ],
            [
                'type' => 'cta',
                'data' => [
                    'eyebrow' => 'Napište nám',
                    'title' => 'Pošlete odkaz na svůj e-shop.',
                    'perex' => 'Do dvou pracovních dnů vám napíšeme, co jsme na něm našli.',
                ],
            ],
        ];
    }
};
