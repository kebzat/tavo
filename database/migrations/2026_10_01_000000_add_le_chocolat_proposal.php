<?php

use App\Models\Proposal;
use App\Support\ResponsiveImage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Potenciální spolupráce: Le Chocolat z Hradce Králové (Shoptet, dovoz Venchi
 * a Amatller, vlastní ruční čokolády z pražské manufaktury).
 *
 * Postavené podle IQ Hraček. Web prošel Tom 1. 10. 2026 (screenshoty, archiv
 * webu z roku 2016, profil obchodu na Heurece). Marketingové body jsou
 * z Pavlových poznámek. Návrh homepage je poskládaný z jejich fotek a produktů.
 *
 * Založí se jen jednou a zůstane nesdílená, dokud ji někdo v nástrojích
 * nezkontroluje a nezapne. Další úpravy patří do administrace, ne sem.
 *
 * Pravidla pro psaní textů: .claude/skills/tavo-copy/SKILL.md
 */
return new class extends Migration
{
    private const SLUG = 'le-chocolat';

    private const IMAGES = [
        'spoluprace/le-chocolat-navrh-homepage.jpg',
        'spoluprace/le-chocolat-web-2016.jpg',
        'spoluprace/le-chocolat-web-2026.jpg',
        'spoluprace/ukazka-horni-lista-venira.jpg',
        'spoluprace/ukazka-doruceni-sparkys.jpg',
        'spoluprace/ukazka-cross-sell-alza.jpg',
        'spoluprace/ukazka-darek-k-objednavce-venira.jpg',
        'spoluprace/ukazka-kosik-rybizak.jpg',
        'spoluprace/ukazka-popup-venira.jpg',
    ];

    public function up(): void
    {
        if (Proposal::where('slug', self::SLUG)->exists()) {
            return;
        }

        foreach (self::IMAGES as $path) {
            $this->copyImage($path);
        }

        Proposal::create([
            'company_name' => 'Le Chocolat',
            'slug' => self::SLUG,
            'title' => 'V čem vám můžeme pomoct?',
            'intro' => 'Prošli jsme váš e-shop, sociální sítě i to, jak jste loni rozjížděli Vánoce. V bodech jsme sepsali, '
                .'co bychom vám pomohli vyřešit: co opravit hned, na čem se dá před sezonou vydělat a co přinese víc až dlouhodobě.',
            'prepared_at' => '2026-10-01',
            // Hodnocení sociálních sítí a marketingu doplní Pavel. Dlaždice bez čísla se nezobrazí.
            'highlights' => [
                ['label' => 'Web', 'value' => '3 z 5'],
                ['label' => 'Sociální sítě', 'value' => null],
                ['label' => 'Výkonnostní marketing', 'value' => null],
            ],

            'timeline_intro' => 'Web Le Chocolat za deset let. Po kliknutí na obrázek se otevře celý.',
            'timeline' => [
                [
                    'label' => '2016',
                    'title' => 'Kdysi',
                    'body' => 'Tmavé fotky s kakaovými boby a pod nimi tři dlaždice: Namixuj si bonbóny, Vánoce a Horká čokoláda. Jednoduché a na svou dobu elegantní.',
                    'image' => $this->stored('spoluprace/le-chocolat-web-2016.jpg'),
                    'image_alt' => 'E-shop Le Chocolat v roce 2016',
                ],
                [
                    'label' => '2026',
                    'title' => 'Dnes',
                    'body' => 'Víc sortimentu a víc bannerů. Hlavní banner ale pořád hlásí letní provoz a produkty jsou v posuvných pásech se šipkami.',
                    'image' => $this->stored('spoluprace/le-chocolat-web-2026.jpg'),
                    'image_alt' => 'Dnešní homepage e-shopu Le Chocolat',
                ],
                [
                    'label' => 'Návrh',
                    'title' => 'S námi',
                    'body' => 'Vánoční nabídka nahoře, dárkový rádce, hodnocení 4,9 z 5 na očích a vlastní bonboniéra na pár kliknutí. Na Shoptetu, bez migrace.',
                    'image' => $this->stored('spoluprace/le-chocolat-navrh-homepage.jpg'),
                    'image_alt' => 'Návrh nové homepage e-shopu Le Chocolat',
                ],
            ],

            'findings_title' => 'Stručné shrnutí',
            'findings_intro' => 'Díváme se zvenku, jako zákazník. Do čísel e-shopu ani reklamních účtů jsme zatím neviděli, '
                .'takže část z toho jsou hypotézy, které si spolu ověříme.',
            'findings' => [
                [
                    'priority' => 'urgent',
                    'tone' => 'problem',
                    'title' => 'Na úvodní stránce pořád visí letní provoz',
                    'body' => 'Hlavní banner hlásí „Odesíláme pouze každé úterý. Hezké léto!“ a je říjen. Kdo přijde vybírat dárek, '
                        .'dozví se jako první, že expedice je omezená. Na jeho místo patří vánoční nabídka a termín, do kdy objednávka dorazí pod stromeček.',
                ],
                [
                    'priority' => 'urgent',
                    'tone' => 'problem',
                    'title' => 'Odznak Heureky, kterou zavedenou nemáte',
                    'body' => 'U hodnocení na webu je logo Heureky „Ověřeno zákazníky“. Heureka ale u vašeho obchodu píše, že službu zavedenou nemáte '
                        .'(ověřeno 1. 10. 2026). Na webu je jen obrázek, bez propojení. Kdo to zkusí dohledat, přijde na to rychle. '
                        .'Přitom máte vlastní hodnocení 4,9 z 5 od 488 zákazníků, které žádný cizí odznak nepotřebuje.',
                ],
                [
                    'priority' => 'urgent',
                    'tone' => 'opportunity',
                    'title' => 'Firemní dárky bez produktů a bez poptávky',
                    'body' => 'Stránka má pěkný text o ruční výrobě, stužce a přebalu s grafikou firmy. Chybí ale ukázky balíčků, orientační ceny '
                        .'a formulář, zbývá jen e-mail. Firmy dárky vybírají v listopadu a jedna objednávka bývá pro celý tým nebo všechny klienty. '
                        .'Kdo musí psát e-mail, aby zjistil cenu, snadno objedná jinde.',
                ],
                [
                    'priority' => 'urgent',
                    'tone' => 'problem',
                    'title' => 'Letos zatím bez sezonní reklamy',
                    'body' => 'Loni jste reklamu pustili v polovině října. Letos zatím nic neběží a do Vánoc zbývají necelé tři měsíce.',
                ],
                [
                    'priority' => 'important',
                    'tone' => 'strength',
                    'title' => '4,9 z 5 od 488 zákazníků, ale až v patičce',
                    'body' => 'Z 488 hodnocení je 470 za plných pět hvězdiček. Na úvodní stránce se ukážou až úplně dole a jen tři nejnovější, '
                        .'dvě z nich od stejného zákazníka. Hodnocení patří nahoru, k produktům a do košíku.',
                ],
                [
                    'priority' => 'important',
                    'tone' => 'opportunity',
                    'title' => 'Na webu nejsou skuteční lidé',
                    'body' => 'Všechny fotky jsou produktové, na bílém nebo barevném pozadí. Nikde není vidět, kdo čokolády vybírá a balí, '
                        .'ruce v pražské manufaktuře ani dárek u někoho doma. Čokoládový dárek se kupuje spíš srdcem a tohle je to, co ho prodá.',
                ],
                [
                    'priority' => 'important',
                    'tone' => 'opportunity',
                    'title' => 'To, čím se lišíte, je schované na podstránkách',
                    'body' => 'Vlastní ruční výroba v Praze, přímý dovoz Venchi a Amatller, balení, které vydrží léto i mráz. '
                        .'Tohle vás odlišuje od supermarketu i od velkých e-shopů. Najít se to dá na stránkách O nás, Velkoobchod a Doprava, '
                        .'na úvodní stránce zbyly tři obecné věty pod bannerem.',
                ],
                [
                    'priority' => 'important',
                    'tone' => 'problem',
                    'title' => 'Produkty v posuvných pásech, na mobilu šipky přes text',
                    'body' => 'Všechny výběry na úvodní stránce jsou pásy se šipkami do stran. Na mobilu šipky zakrývají názvy produktů '
                        .'a zákazník vidí dva kusy najednou. Obyčejná mřížka ukáže víc a nic nezakryje.',
                ],
                [
                    'priority' => 'important',
                    'tone' => 'opportunity',
                    'title' => 'Sociální sítě teď nepracují pro vás',
                    'body' => 'Nevzniká obsah, který by ukazoval hodnocení, doporučoval bestsellery nebo dával vědět o akcích. '
                        .'U obchodu s dárky je to přitom místo, kde lidé hledají inspiraci a ověřují si, komu věřit.',
                ],
                [
                    'priority' => 'later',
                    'tone' => 'opportunity',
                    'title' => 'Bonboniéra, kterou si zákazník poskládá sám',
                    'body' => 'Pralinky prodáváte po kusech za 16 až 34 Kč a na stránce O nás píšete, že bonbóny mícháte podle chuti zákazníka. '
                        .'Online to ale udělat nejde. Skládání vlastní bonboniéry do dárkové krabičky by z drobného nákupu udělalo dárek. '
                        .'V roce 2016 jste „Namixuj si bonbóny“ měli přímo na úvodní stránce.',
                ],
                [
                    'priority' => 'later',
                    'tone' => 'opportunity',
                    'title' => 'Dárkový rádce pro ty, kdo nevědí, co chtějí',
                    'body' => 'Před Vánoci přijde hodně lidí bez jasné představy. Stačí dvě otázky: pro koho (pro sebe, na dárek, pro firmu) '
                        .'a kolik chtějí utratit. Web pak ukáže jen to, co sedí, místo stovek tabulek čokolády.',
                ],
            ],

            'recommendations_intro' => 'Seřazené podle toho, co se může projevit na tržbách ještě letos. Věci, které pomáhají spíš dlouhodobě, jsou na konci.',
            'recommendations' => [
                [
                    'who' => 'Tom',
                    'title' => 'Opravit problémová místa na webu do 14 dní',
                    'body' => 'Vánoční banner místo letního, hodnocení 4,9 z 5 nahoru místo cizího odznaku, mřížka produktů místo posuvných pásů '
                        .'a pás s tím, čím se lišíte. Na Shoptetu, bez migrace a bez výpadku.',
                ],
                [
                    'who' => 'Tom',
                    'title' => 'Firemní dárky, které si firma objedná sama',
                    'body' => 'Stránka s hotovými balíčky, orientační cenou za kus, ukázkami přebalu s logem a krátkou poptávkou: počet kusů, '
                        .'rozpočet na dárek, termín. Hotové do začátku listopadu, kdy firmy vybírají.',
                ],
                [
                    'who' => 'Pavel',
                    'title' => 'Marketingová strategie pro sezonu i mimo ni',
                    'body' => 'Co pustit před Vánoci a na které produkty, a co dělat v létě, kdy se čokoláda špatně posílá. '
                        .'Ať nezačínáte každý rok od nuly.',
                ],
                [
                    'who' => 'Pavel a Tom',
                    'title' => 'Texty a fotky s prémiovým pocitem',
                    'body' => 'Pomůžeme s texty na webu a výhody (rychlost, hodnocení, kvalita, ruční výroba) dostaneme na úvodní stránku, '
                        .'k produktům i do košíku. K tomu pár fotek z manufaktury a z balení, které ukážou lidi za čokoládou.',
                ],
                [
                    'who' => 'Pavel',
                    'title' => 'Povědomí v Hradci Králové s minimálními náklady',
                    'body' => 'Pobavíme se o tom, jak přes sociální sítě dostat Le Chocolat do povědomí lidí v Hradci. '
                        .'Osobní odběr zdarma a to, že jste místní firma, jsou dobrý důvod nakoupit právě u vás.',
                ],
                [
                    'who' => 'Tom',
                    'title' => 'Vlastní bonboniéra a dárkový rádce',
                    'body' => 'Dvě funkce, které z webu udělají místo, kde se dárek vybírá snadno. Na Shoptetu jdou udělat doplňkem nebo vlastním '
                        .'skriptem, přesný rozsah a cenu řekneme po prohlídce administrace.',
                ],
                [
                    'who' => 'Pavel a Tom',
                    'title' => 'Rozvoj e-shopu podle vašich priorit',
                    'body' => 'Po sezoně se podíváme do dat, co fungovalo, a podle toho web upravujeme dál. Když se ukáže, že to má smysl, '
                        .'připravíme celý redesign.',
                ],
            ],

            'steps_intro' => 'Prvních pár týdnů jde o to, co stihne zabrat ještě před Vánoci. Zbytek přijde, až uvidíme první výsledky.',
            'steps' => [
                [
                    'when' => '1.–2. týden',
                    'title' => 'Refresh problémových sekcí webu',
                    'body' => 'Vánoční banner, hodnocení nahoře, mřížka produktů, firemní dárky s poptávkou.',
                    'later' => false,
                ],
                [
                    'when' => '1. týden',
                    'title' => 'Upřesnění měření a práce s daty',
                    'body' => 'Ať je vidět, odkud objednávky přichází a na čem se vydělává.',
                    'later' => false,
                ],
                [
                    'when' => '2. týden',
                    'title' => 'Jednoduchá tvorba pro Meta Ads',
                    'body' => 'Šablony bannerů a krátkých videí k nejsilnějším produktům, které zvládnete obměňovat i sami.',
                    'later' => false,
                ],
                [
                    'when' => '2. týden',
                    'title' => 'Audit dalších marketingových aktivit před sezonou',
                    'body' => 'Projdeme, co dnes děláte, a do sezony pustíme jen to, co dává smysl.',
                    'later' => false,
                ],
                [
                    'when' => '3. týden',
                    'title' => 'Lokální marketing',
                    'body' => 'Podpora obchodu v Hradci Králové a okolí.',
                    'later' => false,
                ],
                [
                    'when' => '3.–4. týden',
                    'title' => 'Spuštění Meta kampaně na nejsilnější produkty',
                    'body' => 'S hodnocením 4,9 z 5 přímo v reklamě. Do Vánoc ji ladíme každý týden.',
                    'later' => false,
                ],
                [
                    'when' => 'Po Vánocích',
                    'title' => 'Ladění webu a případně redesign',
                    'body' => 'Postupně podle dat ze sezony. Vlastní bonboniéra, dárkový rádce a nové fotky.',
                    'later' => true,
                ],
                [
                    'when' => 'Průběžně',
                    'title' => 'Sociální sítě',
                    'body' => 'Rozšiřujeme podle toho, co funguje.',
                    'later' => true,
                ],
            ],

            'examples' => [
                [
                    'placement' => 'after_findings',
                    'kind' => 'Návrh s pomocí AI',
                    'title' => 'Takhle by mohla vypadat homepage před Vánoci',
                    'body' => 'Návrh jsme připravili s pomocí AI z vašich fotek a produktů, abyste viděli směr dřív, než cokoliv zaplatíte. '
                        .'Vánoční nabídka nahoře, dárkový rádce, hodnocení na očích, vlastní bonboniéra a firemní dárky s poptávkou. '
                        .'Místa pro fotky z manufaktury a od zákazníků jsou zatím prázdná, ty nafotíme s vámi. Finální podobu doladíme podle toho, co Shoptet umožní.',
                    'image' => $this->stored('spoluprace/le-chocolat-navrh-homepage.jpg'),
                    'image_alt' => 'Návrh nové homepage e-shopu Le Chocolat',
                    'scroll' => true,
                    'link_url' => null,
                    'link_label' => null,
                ],
                [
                    'placement' => 'after_principles',
                    'kind' => 'Inspirace',
                    'title' => 'Jak to vypadá, když je to zvládnuté',
                    'body' => 'Pár příkladů z e-shopů, které to dělají dobře. Vybrali jsme to, co se hodí k dárkům z čokolády před Vánoci, '
                        .'v pořadí, jak jimi prochází zákazník: od lišty nahoře přes detail produktu po košík. Screenshoty jsou z 30. 9. 2026, po kliknutí se otevřou celé.',
                    'image' => null,
                    'image_alt' => null,
                    'scroll' => false,
                    'link_url' => route('pages.show', 'pro-klienty'),
                    'link_label' => 'Další tipy pro e-shopy',
                    'items' => [
                        $this->item(
                            'Horní lišta: Venira.cz',
                            'Tenký pruh nad hlavičkou s tím, co právě platí. U vás by tam před Vánoci patřil poslední termín doručení '
                                .'pod stromeček a doprava zdarma od 1 200 Kč. V létě pak místo banneru informace o expedici v horku.',
                            'spoluprace/ukazka-horni-lista-venira.jpg',
                            'Horní lišta s odpočtem akce nad hlavičkou e-shopu Venira.cz',
                        ),
                        $this->item(
                            'Stavová lišta: Sparkys.cz',
                            'Rovnou u ceny odpovídá na otázku „kdy to budu mít doma“. U čokolády, kterou v mrazu i v horku posíláte jen v některé dny, '
                                .'tohle zákazníka uklidní víc než tabulka na stránce o dopravě.',
                            'spoluprace/ukazka-doruceni-sparkys.jpg',
                            'Detail produktu na Sparkys.cz s blokem Kdy budu mít zboží doma',
                        ),
                        $this->item(
                            'Nejčastěji kupováno společně: Alza.cz',
                            'K bonboniéře nabídne pralinky po kusech, k tabulce dárkovou tašku nebo kartičku. S tlačítkem do košíku u každé položky.',
                            'spoluprace/ukazka-cross-sell-alza.jpg',
                            'Blok Nejčastěji zakoupeno společně na Alza.cz',
                        ),
                        $this->item(
                            'Dárek k objednávce: Venira.cz',
                            'Pod košíkem ukáže, kolik zbývá do dárku. U vás třeba pralinka navíc nebo balení zdarma. Zákazník si objednávku sám dorovná.',
                            'spoluprace/ukazka-darek-k-objednavce-venira.jpg',
                            'Nabídka dárků k objednávce s ukazatelem, kolik zbývá dokoupit, na Venira.cz',
                        ),
                        $this->item(
                            'Košík, který prodává: Rybizak.cz',
                            'Vedle obsahu košíku ukazuje, kolik zbývá do dopravy zdarma, a dole připomene, na co zákazník mohl zapomenout. '
                                .'U vánočních nákupů hlavně ta lišta do dopravy zdarma.',
                            'spoluprace/ukazka-kosik-rybizak.jpg',
                            'Košík na Rybizak.cz s ukazatelem dopravy zdarma, dárkem a doporučenými produkty',
                        ),
                        $this->item(
                            'Pop-up za kontakt: Venira.cz',
                            'Sleva za přihlášení k newsletteru s jasnou podmínkou. Z kontaktů se pak staví e-maily před Valentýnem, Dnem matek a Vánoci, '
                                .'kdy se čokoláda kupuje nejvíc.',
                            'spoluprace/ukazka-popup-venira.jpg',
                            'Okno se slevou za přihlášení k newsletteru na Venira.cz',
                        ),
                    ],
                ],
            ],

            'experiences' => Proposal::where('slug', 'iq-hracky')->value('experiences') ?? [],

            'principles' => [
                [
                    'title' => 'Nejdřív tržby, pak hezké věci',
                    'body' => 'Začínáme tím, co se může projevit na prodejích ještě letos: vánoční nabídka, firemní dárky a reklama na nejsilnější produkty. '
                        .'Větší změny vzhledu stavíme na datech ze sezony, ať vycházejí z toho, co vaši zákazníci opravdu kupují.',
                ],
                [
                    'title' => 'Každá koruna má úkol',
                    'body' => 'Buď podpoří prodej hned, nebo vám posílí pozici na trhu. Obsah jen pro obsah točit nebudeme.',
                ],
                [
                    'title' => 'AI tam, kde pomáhá',
                    'body' => 'Na návrhy a analýzy ano. U prémiové čokolády ale prodávají skutečné fotky a skutečné ruce, ne obrázky z AI.',
                ],
            ],

            'is_public' => false,
        ]);
    }

    public function down(): void
    {
        // Obrázky necháváme, tipy sdílí i jiné nabídky.
        Proposal::where('slug', self::SLUG)->delete();
    }

    /** @return array{title: string, body: string, image: ?string, image_alt: string, video_url: null} */
    private function item(string $title, string $body, string $image, string $alt): array
    {
        return [
            'title' => $title,
            'body' => $body,
            'image' => $this->stored($image),
            'image_alt' => $alt,
            'video_url' => null,
        ];
    }

    private function stored(string $path): ?string
    {
        return Storage::disk('public')->exists($path) ? $path : null;
    }

    /** Obrázky se do gitu nedostanou přes storage/, leží v database/seeders/assets/. */
    private function copyImage(string $path): void
    {
        $source = database_path('seeders/assets/'.$path);
        $disk = Storage::disk('public');

        if (! File::isFile($source) || $disk->exists($path)) {
            return;
        }

        $disk->put($path, File::get($source));
        ResponsiveImage::generate($path);
    }
};
