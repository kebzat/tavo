<?php

use App\Models\Proposal;
use App\Support\ResponsiveImage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * První potenciální spolupráce: IQ Hračky z Hradce Králové (Shoptet).
 * Podklad jsou Tomovy a Pavlovy poznámky z 30. 9. 2026 a návrh homepage
 * připravený s pomocí AI.
 *
 * Založí se jen jednou a zůstane nesdílená, dokud ji někdo v nástrojích
 * nezkontroluje a nezapne. Další úpravy patří do administrace, ne sem.
 *
 * Pravidla pro psaní textů: .claude/skills/tavo-copy/SKILL.md
 */
return new class extends Migration
{
    private const SLUG = 'iq-hracky';

    private const IMAGE = 'spoluprace/iq-hracky-navrh-homepage.jpg';

    public function up(): void
    {
        if (Proposal::where('slug', self::SLUG)->exists()) {
            return;
        }

        $this->copyImage();

        Proposal::create([
            'company_name' => 'IQ Hračky',
            'slug' => self::SLUG,
            'title' => 'Co bychom s IQ Hračkami udělali do Vánoc a co potom',
            'intro' => 'Prošli jsme web, Heureku i Instagram. Níž najdete, co jsme našli, co bychom řešili hned a co může počkat na leden. '
                .'Nejdřív to, co může pomoct tržbám ještě letos. Hezké věci na dlouho až potom.',
            'prepared_at' => '2026-09-30',
            'highlights' => [
                ['value' => '5 / 5', 'label' => 'hodnocení obchodu na Heurece'],
                ['value' => '2 019', 'label' => 'recenzí, které web skoro neukazuje'],
                ['value' => '12 let', 'label' => 'staré logo'],
            ],

            'findings_intro' => 'Díváme se zvenku, jako zákazník. Do čísel e-shopu ani reklamních účtů jsme zatím neviděli, '
                .'takže část z toho jsou hypotézy, které si spolu ověříme.',
            'findings' => [
                [
                    'tone' => 'strength',
                    'title' => 'Na Heurece máte 5 z 5 a přes 2 000 recenzí',
                    'body' => 'Tohle si většina e-shopů s hračkami teprve buduje. Na webu to ale skoro není vidět. '
                        .'Rodič, který o vás slyší poprvé, se to dozví jen tehdy, když si vás sám dohledá.',
                ],
                [
                    'tone' => 'problem',
                    'title' => 'Na homepage zůstalo „ChatGPT řekl“',
                    'body' => 'Zbytek textu zkopírovaného z ChatGPT. Oprava zabere minutu. Dokud tam je, vypadá to, '
                        .'že se o web nikdo nestará, a u obchodu s dárky pro děti to bolí.',
                ],
                [
                    'tone' => 'problem',
                    'title' => 'AI bannery ubírají důvěru',
                    'body' => 'Rodiče dnes obrázek z AI poznají na první pohled. U hraček, kde řeší kvalitu a bezpečnost, '
                        .'to spíš odrazuje. Fotka skutečné hračky v rukou dítěte stojí málo a řekne víc.',
                ],
                [
                    'tone' => 'problem',
                    'title' => 'Logo je 12 let staré a web podle toho vypadá',
                    'body' => 'Obchod působí starší, než ve skutečnosti je. Nový vzhled přitom nemusí znamenat nový e-shop. '
                        .'Na Shoptetu jde web osvěžit bez migrace.',
                ],
                [
                    'tone' => 'opportunity',
                    'title' => 'Není poznat, proč nakoupit právě u vás',
                    'body' => 'Na první obrazovce chybí důvody, které vás odliší od velkých obchodů: hodnocení, rychlost odeslání, '
                        .'hračky vybrané pro zvídavé děti. Právě ty si lidé se značkou spojí a vrátí se.',
                ],
                [
                    'tone' => 'problem',
                    'title' => 'Košík ukáže jen poslední přidanou hračku',
                    'body' => 'Po přidání do košíku vyskočí okno jen s tím, co jste právě přidali. Kdo kupuje víc dárků najednou, '
                        .'nevidí, co už v košíku má, ani kolik za všechno zaplatí.',
                ],
                [
                    'tone' => 'opportunity',
                    'title' => 'Chybí stránka o vás',
                    'body' => 'Obchod z Hradce Králové, který hračky vybírá a zná, je dobrý příběh. Na webu ale není, kdo za ním stojí. '
                        .'U dárků pro děti lidé chtějí vědět, od koho kupují.',
                ],
                [
                    'tone' => 'opportunity',
                    'title' => 'Instagram spí, přitom jsou tam vaše zákaznice',
                    'body' => 'Nejsilnější skupinou budou nejspíš maminky mezi 25 a 40 lety. Dárky vybírají i na Instagramu. '
                        .'Profil bez nových příspěvků vypadá jako zavřený obchod.',
                ],
                [
                    'tone' => 'strength',
                    'title' => 'Reklamy vám už jednou fungovaly',
                    'body' => 'Víme tedy, že poptávka je a lidé na reklamu reagují. Před Vánoci je škoda ji nechat ležet.',
                ],
            ],

            'recommendations_intro' => 'Seřazené podle toho, co se může projevit na tržbách ještě letos. '
                .'Věci, které pomáhají spíš dlouhodobě, jsou na konci.',
            'recommendations' => [
                [
                    'title' => 'Osvěžit web, nestavět nový',
                    'who' => 'Tom',
                    'body' => 'Do 14 dní od předání přístupů upravíme homepage na Shoptetu. Hodnocení z Heureky nahoru, pás s důvody nakoupit, '
                        .'jednoduchý výběr hračky podle toho, pro koho a pro jaký věk je, a košík, který ukáže celý nákup. Bez migrace a bez výpadku.',
                ],
                [
                    'title' => 'Z hodnocení udělat hlavní argument',
                    'who' => 'Pavel a Tom',
                    'body' => '5 z 5 a přes 2 000 recenzí patří na homepage, k produktům, do košíku i do reklam. Je to nejlevnější důvěra, kterou máte.',
                ],
                [
                    'title' => 'Reklama na produkty, které vydělávají',
                    'who' => 'Pavel',
                    'body' => 'Meta kampaně pustíme na produkty s nejlepším poměrem prodejů a marže, ať každá objednávka z reklamy něco vydělá. '
                        .'Bannery i krátká videa k nim připravíme. Podle výsledků přidáme YouTube nebo TikTok.',
                ],
                [
                    'title' => 'Audit Google Ads před sezonou',
                    'who' => 'Pavel s partnerem na Google Ads',
                    'body' => 'Projdeme, kam teď v Google Ads jdou peníze, co vypnout a co přes Vánoce posílit. '
                        .'Když bude dávat smysl kampaně škálovat, řekneme jak.',
                ],
                [
                    'title' => 'Místní tvůrkyně místo AI bannerů',
                    'who' => 'Pavel a externí tvůrkyně',
                    'body' => 'Najdeme maminku z okolí, která se nebojí kamery a natočí hračky se svými dětmi. Při dlouhodobé spolupráci '
                        .'se dá domluvit dobrá cena nebo barter za hračky. Jedno natáčení vystačí na Instagram i na reklamy.',
                ],
                [
                    'title' => 'Instagram se směrem',
                    'who' => 'Pavel',
                    'body' => 'Řekneme, co točit, jak často a proč, a pomůžeme s prvními příspěvky. Obsah, který nic nepřinese, vám přidávat nebudeme.',
                ],
                [
                    'title' => 'Značka, kterou si rodiče zapamatují',
                    'who' => 'Pavel a Tom',
                    'body' => 'Nové logo, barvy a pár pravidel, jak mluvíte. Na listopad to nespěchá. Bez toho se ale každý banner '
                        .'vymýšlí znovu od nuly a značka se lidem nevybaví.',
                ],
                [
                    'title' => 'Udržet zákazníky, které už máte',
                    'who' => 'Pavel',
                    'body' => 'Automatické e-maily po nákupu a retargeting na lidi, kteří odešli z košíku. Nastavíme podle potřeby, až poběží hlavní kampaně.',
                ],
            ],

            'steps_intro' => 'Prvních pár týdnů jde o to, co podchytí sezonu až do Vánoc. Zbytek přijde, až uvidíme první výsledky.',
            'steps' => [
                [
                    'when' => '1.–2. týden',
                    'title' => 'Refresh webu',
                    'body' => 'Hodnocení z Heureky, důvody nakoupit, výběr hračky podle věku a lepší košík. Pryč s „ChatGPT řekl“ a s AI bannery.',
                    'later' => false,
                ],
                [
                    'when' => '1. týden',
                    'title' => 'Audit Google Ads',
                    'body' => 'Projdeme účet a do sezony pustíme jen to, co dává smysl.',
                    'later' => false,
                ],
                [
                    'when' => '2. týden',
                    'title' => 'Jednoduchá tvorba pro Meta Ads',
                    'body' => 'Šablony bannerů a první krátká videa k nejsilnějším produktům. Nastavené tak, abyste je zvládli obměňovat i sami.',
                    'later' => false,
                ],
                [
                    'when' => '3.–4. týden',
                    'title' => 'Spuštění Meta kampaní',
                    'body' => 'Na nejsilnější produkty, s hodnocením z Heureky přímo v reklamě. Do Vánoc je ladíme každý týden.',
                    'later' => false,
                ],
                [
                    'when' => 'Po Vánocích',
                    'title' => 'Ladění webu',
                    'body' => 'Podle dat ze sezony web upravujeme dál. Když se ukáže, že to má smysl, připravíme celý redesign.',
                    'later' => true,
                ],
                [
                    'when' => 'Průběžně',
                    'title' => 'Instagram a sociální sítě',
                    'body' => 'Tvůrkyně, pravidelný obsah a povědomí mezi rodiči. Rozšiřujeme podle toho, co funguje.',
                    'later' => true,
                ],
            ],

            'examples' => [
                [
                    'placement' => 'after_findings',
                    'kind' => 'Návrh s pomocí AI',
                    'title' => 'Takhle by mohla vypadat homepage po refreshi',
                    'body' => 'Návrh jsme připravili s pomocí AI, abyste viděli směr dřív, než cokoliv zaplatíte. Hodnocení z Heureky nahoře, '
                        .'výběr hračky podle věku, důvody nakoupit a rady pro rodiče. Finální podobu doladíme s vámi a podle toho, co Shoptet umožní.',
                    'image' => File::isFile(Storage::disk('public')->path(self::IMAGE)) ? self::IMAGE : null,
                    'image_alt' => 'Návrh nové homepage e-shopu IQ Hračky',
                    'scroll' => true,
                    'link_url' => null,
                    'link_label' => null,
                ],
                [
                    'placement' => 'after_recommendations',
                    'kind' => 'Foto a video',
                    'title' => 'Místo AI bannerů krátká videa s dětmi',
                    'body' => 'Dítě otevře Elixírovou laboratoř, míchá a ono to svítí. Patnáct vteřin natočených na telefon, bez studia. '
                        ."Takové video jde na Instagram i do reklamy a rodič na něm vidí, co kupuje.\n\n"
                        .'Ukázky od jiných e-shopů s hračkami, ze kterých bychom vycházeli, vám rádi pustíme na hovoru.',
                    'image' => null,
                    'image_alt' => null,
                    'scroll' => false,
                    'link_url' => null,
                    'link_label' => null,
                ],
            ],

            'principles' => [
                [
                    'title' => 'Nejdřív tržby, pak hezké věci',
                    'body' => 'Do Vánoc řešíme to, co se může projevit na prodejích. Značka, redesign a obsah na dlouho přijdou, až bude jasné, na čem stavět.',
                ],
                [
                    'title' => 'Každá koruna má úkol',
                    'body' => 'Buď podpoří prodej hned, nebo vám posílí pozici na trhu. Obsah jen pro obsah točit nebudeme.',
                ],
                [
                    'title' => 'AI tam, kde pomáhá',
                    'body' => 'Na návrhy a analýzy ano. Na bannerech a v textech pro rodiče ji vypínáme, tam důvěru spíš bere.',
                ],
            ],

            'is_public' => false,
        ]);
    }

    public function down(): void
    {
        // Obrázek necháváme: správce mohl mezitím nahrát vlastní se stejným názvem.
        Proposal::where('slug', self::SLUG)->delete();
    }

    /** Obrázky se do gitu nedostanou přes storage/, leží v database/seeders/assets/. */
    private function copyImage(): void
    {
        $source = database_path('seeders/assets/'.self::IMAGE);
        $disk = Storage::disk('public');

        if (! File::isFile($source) || $disk->exists(self::IMAGE)) {
            return;
        }

        $disk->put(self::IMAGE, File::get($source));
        ResponsiveImage::generate(self::IMAGE);
    }
};
