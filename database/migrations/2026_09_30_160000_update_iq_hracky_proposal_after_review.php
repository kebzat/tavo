<?php

use App\Models\Proposal;
use App\Support\ResponsiveImage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * IQ Hračky po připomínkách kolegy z marketingu (30. 9. 2026): nový nadpis
 * a úvod, tři pilíře s hodnocením místo čísel, přepsaná zjištění
 * a doporučení, videa jako ukázka formátu, naše zkušenosti a na konci
 * příklady dobře zvládnutých e-shopů. Návrh homepage má nový banner.
 *
 * Přepisuje jen to, na co správce v administraci nesáhl: každé pole se
 * porovná s otiskem hodnoty, kterou tam zapsaly migrace 120000 a 140000.
 * Když nesedí, pole zůstane, jak ho správce upravil. Nová pole (nadpis
 * sekce 01, zkušenosti) se doplní, jen když jsou prázdná. Obrázek návrhu se
 * vymění všude, kde pořád stojí ten původní.
 *
 * Pravidla pro psaní textů: .claude/skills/tavo-copy/SKILL.md
 */
return new class extends Migration
{
    private const SLUG = 'iq-hracky';

    private const OLD_DESIGN = 'spoluprace/iq-hracky-navrh-homepage.jpg';

    private const NEW_DESIGN = 'spoluprace/iq-hracky-navrh-homepage-2.jpg';

    /**
     * Otisk (sha1) hodnoty z minulých migrací, viz fingerprint(). Obrázky
     * se do otisku nepočítají, řeší se zvlášť výměnou návrhu.
     */
    private const UNTOUCHED = [
        'title' => '0a46ed2de502d54a355848a0365ed64628494ce4',
        'intro' => '381cc7b38cc0632656aac19f3d16e43487dc4d33',
        'highlights' => 'e11b904b04534afc8b5b7af0b70a9d3a3e8d925d',
        'findings' => '54339d99340d0a99f15304624035435529d0e086',
        'recommendations' => 'b21aaf7d89f5519751778aa2332fe22f5480af0e',
        'examples' => '9505354dcac11363701ab6d5b5709d595bbc7747',
        'principles' => '092cdb328f3a702c1b7a509aebd910b826b9e6ec',
    ];

    /** Screenshoty cizích e-shopů pro závěrečnou ukázku, pořízené 30. 9. 2026. */
    private const SHOWCASE = [
        [
            'title' => 'Košík: Pompo.cz',
            'body' => 'Po přidání hračky se vysune celý košík, ne jen poslední kus. Vedle nabídne podložky, které se ke stavebnici hodí, '
                .'a dole ukáže, kolik zbývá do dopravy zdarma.',
            'image' => 'spoluprace/ukazka-kosik-pompo.jpg',
            'image_alt' => 'Vysouvací košík na Pompo.cz s doporučenými produkty a lištou dopravy zdarma',
        ],
        [
            'title' => 'Stavová lišta: Sparkys.cz',
            'body' => 'Rovnou u ceny odpovídá na otázku „kdy to budu mít doma“: předpokládané doručení, odběr na pobočce dnes od 17:00 '
                .'a platba později. Před Vánoci přesně to rodiče řeší.',
            'image' => 'spoluprace/ukazka-doruceni-sparkys.jpg',
            'image_alt' => 'Detail produktu na Sparkys.cz s blokem Kdy budu mít zboží doma',
        ],
        [
            'title' => 'Doplňkové produkty: Alza.cz',
            'body' => 'Ke stavebnici nabízí věci, které se kupují společně: úložné boxy, příslušenství, police. Rozdělené do záložek, '
                .'s hodnocením a tlačítkem do košíku přímo u každého.',
            'image' => 'spoluprace/ukazka-cross-sell-alza.jpg',
            'image_alt' => 'Blok Nejčastěji zakoupeno společně na produktu LEGO Classic na Alza.cz',
        ],
        [
            'title' => 'Popis produktu: běžný a prémiový (Alza.cz)',
            'body' => 'Stejná stavebnice LEGO Classic 10696. Vlevo odstavec od výrobce, jaký má většina obchodů. Vpravo popis na Alza.cz: '
                .'fotky dětí při hraní, pro koho sada je a co rozvíjí, a pod tím v kostce počet dílků, věk a návod.',
            'image' => 'spoluprace/ukazka-popis-produktu.jpg',
            'image_alt' => 'Srovnání běžného a prémiového popisu stavebnice LEGO Classic 10696',
        ],
    ];

    public function up(): void
    {
        $proposal = Proposal::where('slug', self::SLUG)->first();

        if (! $proposal) {
            return;
        }

        foreach ([self::NEW_DESIGN, ...array_column(self::SHOWCASE, 'image')] as $path) {
            $this->copyImage($path);
        }

        foreach ($this->content() as $field => $value) {
            if ($this->fingerprint($proposal->getAttribute($field)) === self::UNTOUCHED[$field]) {
                $proposal->setAttribute($field, $value);
            }
        }

        if (blank($proposal->findings_title)) {
            $proposal->findings_title = 'Stručné shrnutí';
        }

        if (blank($proposal->experiences)) {
            $proposal->experiences = $this->experiences();
        }

        $proposal->timeline = $this->swapDesign($proposal->timeline);
        $proposal->examples = $this->swapDesign($proposal->examples);

        $proposal->save();
    }

    public function down(): void
    {
        // Obsah zpátky nevracíme: správce ho mohl mezitím dál upravovat
        // a starou verzi textů najde v migracích 120000 a 140000.
    }

    /** @return array<string, mixed> */
    private function content(): array
    {
        return [
            'title' => 'V čem vám můžeme pomoct?',
            'intro' => 'Prošli jsme váš web, srovnávače zboží, knihovny reklam i sociální sítě. V bodech jsme sepsali, co bychom vám pomohli vyřešit: '
                .'kde se dá rychle vytěžit víc z toho, co už máte, a co může být riziko do budoucna. Web, sociální sítě a výkonnostní marketing jsme ohodnotili od 1 do 5.',
            'highlights' => [
                ['value' => '3 z 5', 'label' => 'Web'],
                ['value' => '1 z 5', 'label' => 'Sociální sítě'],
                ['value' => '2 z 5', 'label' => 'Výkonnostní marketing'],
            ],

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
                    'title' => 'Web není přívětivý a značka nemá vlastní tvář',
                    'body' => 'Obchod působí starší a strožejší, než ve skutečnosti je. Barvy, bannery a fotky nemají jednotný styl, '
                        .'takže si značku lidé nemají podle čeho zapamatovat, na webu ani v reklamě. Logo, ke kterému máte vztah, může zůstat. '
                        .'O nákupu rozhoduje celkový dojem. Na Shoptetu jde vzhled osvěžit bez migrace.',
                ],
                [
                    'tone' => 'opportunity',
                    'title' => 'Není poznat, proč nakoupit právě u vás',
                    'body' => 'Na první obrazovce chybí důvody, které vás odliší od velkých obchodů: hodnocení, rychlost odeslání, '
                        .'hračky vybrané pro zvídavé děti. Právě ty si lidé se značkou spojí a vrátí se.',
                ],
                [
                    'tone' => 'problem',
                    'title' => 'Košík ukáže jen poslední hračku a nic nepřidá',
                    'body' => 'Po přidání do košíku vyskočí okno jen s tím, co jste právě přidali. Kdo kupuje víc dárků najednou, nevidí, co už v košíku má. '
                        .'U produktu web ukáže podobné hračky, doplňky k té vybrané ale nenabídne: rozšiřující sadu, náplň nebo drobnost k dárku. '
                        .'Na obou místech se dá zvednout průměrná hodnota objednávky.',
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
                    'tone' => 'opportunity',
                    'title' => 'Nevyužitá prodejní reklama na Facebooku a Instagramu',
                    'body' => 'V knihovně reklam Meta je vidět, že kampaně běžely a pak skončily. Jak si vedly, zvenku nevíme. '
                        .'Cílená prodejní reklama na rodiče je přitom cesta, jak obrat škálovat, a teď ji nevyužíváte.',
                ],
                [
                    'tone' => 'opportunity',
                    'title' => 'Google Ads bez reklam na YouTube',
                    'body' => 'Google Ads se dají rozšířit o video reklamy na YouTube. Chybí k nim ale obsah: krátká videa s hračkami a dětmi. '
                        .'Když je natočíme, poslouží i na Instagramu a Facebooku.',
                ],
            ],

            'recommendations' => [
                [
                    'title' => 'Osvěžit web, nestavět nový',
                    'who' => 'Tom',
                    'body' => 'Do 14 dní od předání přístupů upravíme homepage na Shoptetu. Hodnocení z Heureky nahoru, pás s důvody nakoupit '
                        .'a jednoduchý výběr hračky podle toho, pro koho a pro jaký věk je. Košík doplníme o doporučené produkty a stavovou lištu '
                        .'s aktuálními informacemi: kolik zbývá do dopravy zdarma, kdy balík odejde a jestli dorazí do Vánoc. Bez migrace a bez výpadku.',
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
                        .'Bannery i krátká videa k nim připravíme. Podle výsledků přidáme YouTube nebo TikTok. '
                        .'Jsme z Hradce Králové jako vy, takže focení a natáčení zajistíme na místě, když bude potřeba.',
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
                    'title' => 'Sociální sítě jako prodejní kanál',
                    'who' => 'Pavel',
                    'body' => 'Profily na Instagramu a Facebooku nastavíme jako výkladní skříň, která nepotřebuje denní příspěvky. '
                        .'Ukáže zkušenosti zákazníků a vaše výhody, a až poběží reklamy, přibudou tam i ty nejlepší z nich. '
                        .'Rodič, který na vás narazí poprvé, tam najde důvod vám věřit.',
                ],
                [
                    'title' => 'Značka, kterou si rodiče zapamatují',
                    'who' => 'Pavel a Tom',
                    'body' => 'Nové logo, barvy a pár pravidel, jak mluvíte. Na listopad to nespěchá. Bez toho se ale každý banner '
                        .'vymýšlí znovu od nuly a značka se lidem nevybaví.',
                ],
                [
                    'title' => 'Pracovat se zákazníky, které už máte',
                    'who' => 'Pavel',
                    'body' => 'Získat nového zákazníka stojí víc než udržet stávajícího. Proto nastavíme automatické e-maily, které nekončí '
                        .'potvrzením objednávky a prosbou o hodnocení. Přijdou ve správnou chvíli, nabídnou, co se ke koupené hračce hodí, '
                        .'a připomenou sezonní akce a svátky, od Dne dětí po Vánoce.',
                ],
            ],

            'examples' => [
                [
                    'placement' => 'after_findings',
                    'kind' => 'Návrh s pomocí AI',
                    'title' => 'Takhle by mohla vypadat homepage po refreshi',
                    'body' => 'Návrh jsme připravili s pomocí AI, abyste viděli směr dřív, než cokoliv zaplatíte. Hodnocení z Heureky nahoře, '
                        .'výběr hračky podle věku, důvody nakoupit a rady pro rodiče. Finální podobu doladíme s vámi a podle toho, co Shoptet umožní.',
                    'image' => $this->stored(self::NEW_DESIGN),
                    'image_alt' => 'Návrh nové homepage e-shopu IQ Hračky',
                    'scroll' => true,
                    'link_url' => null,
                    'link_label' => null,
                ],
                [
                    'placement' => 'after_recommendations',
                    'kind' => 'Foto a video',
                    'title' => 'Přirozené reklamy, které fungují na vaši cílovou skupinu',
                    'body' => 'Po domluvě připravíme obsah pro lidi, kteří u vás nakupují. S dětmi a skutečnými rodiči, '
                        ."natočený podle toho, co na sociálních sítích zrovna funguje.\n\n"
                        .'Videa níž nejsou naše. Jsou to reklamy jiných značek s hračkami a dětským nábytkem a ukazují formát, '
                        .'který funguje. Podobná videa pro vás zvládneme natočit bez problému.',
                    'image' => null,
                    'image_alt' => null,
                    'scroll' => false,
                    'link_url' => null,
                    'link_label' => null,
                    'items' => [
                        [
                            'title' => 'První reakce',
                            'body' => 'Dítě uvidí dárek poprvé. Žádný scénář, jen radost.',
                            'video_url' => 'https://drive.google.com/file/d/1bXSmQEYlxGESi5eK3HyNGqSC518WUO6M/view',
                        ],
                        [
                            'title' => 'Rozbalování doma',
                            'body' => 'Krabice dorazí a dítě ji nenechá na pokoji.',
                            'video_url' => 'https://drive.google.com/file/d/1WnBwA_3Lcz8GRLc6iK2htmjU4TNtINR3/view',
                        ],
                        [
                            'title' => 'Hračka v akci',
                            'body' => 'Detail, jak hračka funguje, natočený na telefon.',
                            'video_url' => 'https://drive.google.com/file/d/1H6hMtW6bz3MmlvgIHb4pn1ix_BN7EpCs/view',
                        ],
                        [
                            'title' => 'Hraní v pokojíčku',
                            'body' => 'Dítě si hraje, rodič natáčí. Studio není potřeba.',
                            'video_url' => 'https://drive.google.com/file/d/1_rMrdCnKlQoO3dSRPUXF5cf3PUyvX5jP/view',
                        ],
                    ],
                ],
                [
                    'placement' => 'after_principles',
                    'kind' => 'Inspirace',
                    'title' => 'Jak to vypadá, když je to zvládnuté',
                    'body' => 'Pár příkladů z e-shopů, které to dělají dobře: košík, stavová lišta, doplňkové produkty a popis produktu. '
                        .'Screenshoty jsou z 30. 9. 2026, po kliknutí se otevřou celé.',
                    'image' => null,
                    'image_alt' => null,
                    'scroll' => false,
                    'link_url' => null,
                    'link_label' => null,
                    'items' => array_map(fn (array $item): array => [
                        ...$item,
                        'image' => $this->stored($item['image']),
                    ], self::SHOWCASE),
                ],
            ],

            'principles' => [
                [
                    'title' => 'Nejdřív tržby, pak hezké věci',
                    'body' => 'Začínáme tím, co se může projevit na prodejích ještě letos: košík, prodejní reklama a hodnocení z Heureky. '
                        .'Větší změny vzhledu a značky stavíme na datech ze sezony, ať vycházejí z toho, co vaši zákazníci opravdu kupují.',
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
        ];
    }

    /**
     * Zkušenosti od Toma, věcně beze změny, jen s opravenou typografií.
     * `emphasis` jsou části textu, které se na webu zvýrazní.
     *
     * @return list<array{text: string, emphasis: list<string>}>
     */
    private function experiences(): array
    {
        $rows = [
            [
                'Naše Meta Ads u klientů získávají v průměru 3–10 Kč z každé investované koruny, dle segmentu. Za 10 000 Kč zvýšení obratu o 30 000 až 100 000 Kč.',
                ['3–10 Kč', '30 000 až 100 000 Kč'],
            ],
            [
                'Rozjezd UGC kampaní už několikrát zachránil chod e-shopu nebo znásobil obraty získané z výkonnostních kampaní.',
                ['zachránil chod e-shopu', 'znásobil obraty'],
            ],
            [
                'Optimalizace webu dle našich dat dokáže zvýšit průměrnou objednávku i o několik set Kč.',
                ['o několik set Kč'],
            ],
            [
                'Práce se zákazníky skrze mailing může tvořit až 40 % obratu (automatizovaně získané nákupy).',
                ['až 40 % obratu'],
            ],
            [
                'Strategie a správný obsah na sociálních sítích dokážou za 10 000 Kč zasáhnout až čtvrt milionu lidí.',
                ['až čtvrt milionu lidí'],
            ],
            [
                'Kombinace PPC s Meta Ads kampaněmi dokáže škálovat i samotné výsledky v Google Ads (zvýšené povědomí značky).',
                ['i samotné výsledky v Google Ads'],
            ],
            [
                'Správně zacílená spolupráce společně s reklamní strategií dokáže vyprodat během pár týdnů skladové zásoby produktů.',
                ['během pár týdnů'],
            ],
            [
                'Povedený redesign a úpravy webu dokážou zvýšit konverzní poměr nákupů o několik procent.',
                ['o několik procent'],
            ],
        ];

        return array_map(fn (array $row): array => [
            'text' => $this->nbsp($row[0]),
            'emphasis' => array_map($this->nbsp(...), $row[1]),
        ], $rows);
    }

    /** Pevná mezera v číslech a před jednotkou: „10 000 Kč“, „40 %“ se nerozdělí na dva řádky. */
    private function nbsp(string $text): string
    {
        return (string) preg_replace('/(\d) (?=\d{3}\b|Kč|%)/u', "$1\u{00A0}", $text);
    }

    /**
     * Otisk hodnoty z databáze nezávislý na tom, jak ji uložila administrace:
     * bez prázdných klíčů a bez cest k obrázkům, klíče seřazené, řádky
     * repeateru jako seznam.
     */
    private function fingerprint(mixed $value): string
    {
        $normalize = function (mixed $value) use (&$normalize): mixed {
            if (! is_array($value)) {
                return $value;
            }

            if ($value !== [] && collect($value)->every(fn ($item): bool => is_array($item))) {
                return array_map($normalize, array_values($value));
            }

            $value = array_filter($value, fn ($item, $key): bool => $item !== null && $item !== '' && $key !== 'image', ARRAY_FILTER_USE_BOTH);
            ksort($value);

            return array_map($normalize, $value);
        };

        return sha1((string) json_encode($normalize(json_decode((string) json_encode($value), true)), JSON_UNESCAPED_UNICODE));
    }

    /**
     * Řádky, kde pořád visí původní návrh homepage, dostanou nový.
     * Vlastní obrázek od správce zůstane.
     */
    private function swapDesign(mixed $rows): mixed
    {
        if (! is_array($rows) || ! $this->stored(self::NEW_DESIGN)) {
            return $rows;
        }

        return array_map(function ($row) {
            if (is_array($row) && ($row['image'] ?? null) === self::OLD_DESIGN) {
                $row['image'] = self::NEW_DESIGN;
            }

            return $row;
        }, $rows);
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
