<?php

use App\Support\ResponsiveImage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Stránka /pro-klienty: tipy pro e-shopy, které se nevešly do konkrétní
 * nabídky spolupráce. Stručně, jeden tip na blok, a drobnosti v odrážkách
 * (část inspirovaná výčtem na petrpan.cz/shoptet, přepsaná po svém).
 *
 * Je pro klienty, ne pro Google: vypnutá indexace (noindex, mimo mapu webu).
 * Screenshoty cizích e-shopů jsou z 30. 9. 2026.
 *
 * Stejně jako /e-shop: založí se jen jednou, další úpravy patří do
 * administrace (Obsah → Stránky).
 *
 * Pravidla pro psaní textů: .claude/skills/tavo-copy/SKILL.md
 */
return new class extends Migration
{
    private const SLUG = 'pro-klienty';

    private const IMAGES = [
        'spoluprace/ukazka-darek-k-objednavce-venira.jpg',
        'spoluprace/ukazka-popup-venira.jpg',
        'spoluprace/ukazka-doprava-rybizak.jpg',
        'spoluprace/ukazka-vyhledavani-rybizak.jpg',
        'spoluprace/ukazka-cross-sell-alza.jpg',
    ];

    public function up(): void
    {
        if (DB::table('pages')->where('slug', self::SLUG)->exists()) {
            return;
        }

        foreach (self::IMAGES as $path) {
            $this->copyImage($path);
        }

        DB::table('pages')->insert([
            'slug' => self::SLUG,
            'title' => 'Tipy pro e-shopy',
            'perex' => 'Nápady, které jsme viděli fungovat na jiných e-shopech. Ne každý se hodí všude, proto je klientům vybíráme '
                .'podle toho, co prodávají a kdy. Screenshoty jsou z 30. 9. 2026.',
            'seo_title' => 'Tipy pro e-shopy',
            'published' => true,
            'indexable' => false,
            'hero_cta' => false,
            'blocks' => json_encode($this->blocks(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Obrázky necháváme, sdílí je i nabídky spolupráce.
        DB::table('pages')->where('slug', self::SLUG)->delete();
    }

    /** @return list<array{type: string, data: array<string, mixed>}> */
    private function blocks(): array
    {
        return [
            $this->tip(
                'spoluprace/ukazka-darek-k-objednavce-venira.jpg',
                'left',
                'Venira.cz',
                'Dárek k objednávce',
                'Pod košíkem nabídka dárků podle hodnoty objednávky a u každého, kolik zbývá dokoupit. Zákazník si objednávku '
                    .'sám dorovná, aby dárek dostal. Stejně jde pracovat s dopravou zdarma.',
                'Nabídka dárků k objednávce s ukazatelem, kolik zbývá dokoupit, na Venira.cz',
            ),
            $this->tip(
                'spoluprace/ukazka-popup-venira.jpg',
                'right',
                'Venira.cz',
                'Pop-up za kontakt',
                'Sleva 100 Kč za přihlášení k newsletteru s jasnou podmínkou: platí od objednávky nad 1 000 Kč. Z kontaktů se pak '
                    .'staví e-mailing a automatické kampaně. Dobře nastavené okno nevyskočí hned po otevření stránky a na mobilu '
                    .'nezakryje celý obsah.',
                'Okno se slevou za přihlášení k newsletteru na Venira.cz',
            ),
            $this->tip(
                'spoluprace/ukazka-doprava-rybizak.jpg',
                'left',
                'Rybizak.cz',
                'Stránka o dopravě a platbě',
                'Všichni dopravci a platby na jednom místě i s cenami, doprava zdarma od 1 299 Kč a kolik balíků odejde do druhého '
                    .'dne. Zákazník zjistí cenu dopravy dřív, než začne vyplňovat objednávku. Stačí odkaz v hlavičce nebo u produktu.',
                'Přehled dopravy a plateb s cenami na Rybizak.cz',
            ),
            $this->tip(
                'spoluprace/ukazka-vyhledavani-rybizak.jpg',
                'right',
                'Rybizak.cz',
                'Chytré vyhledávání',
                'Po kliknutí na lupu se otevře okno s oblíbenými kategoriemi, častými dotazy a bestsellery, ještě než zákazník '
                    .'cokoli napíše. U velkého sortimentu se hodí vyhledávání s AI, které rozumí i dotazu „dárek pro holku na 6 let“.',
                'Okno vyhledávání s populárními kategoriemi a bestsellery na Rybizak.cz',
            ),
            $this->tip(
                'spoluprace/ukazka-cross-sell-alza.jpg',
                'left',
                'Alza.cz',
                'Nejčastěji kupováno společně',
                'K produktu nabídne věci, které se kupují spolu s ním: příslušenství, doplňky, úložné boxy. Rozdělené do záložek, '
                    .'s hodnocením a tlačítkem do košíku u každého.',
                'Blok Nejčastěji zakoupeno společně na Alza.cz',
            ),
            [
                'type' => 'bullets',
                'data' => [
                    'tone' => 'ink',
                    'title' => 'Drobnosti, které se vyplatí',
                    'perex' => 'Menší úpravy, kvůli kterým není potřeba nový web.',
                    'columns' => [
                        [
                            'label' => 'Při nákupu',
                            'items' => [
                                'Tlačítko Do košíku, které na mobilu zůstane na očích i při scrollování',
                                'Pás jistot pod tlačítkem: doprava, vrácení, hodnocení obchodu',
                                'Zvýrazněná dostupnost a termín doručení',
                                'Slevový kód, který se uplatní sám po kliknutí na odkaz z e-mailu',
                                'Kratší objednávka na mobilu',
                            ],
                        ],
                        [
                            'label' => 'Po nákupu a v zákulisí',
                            'items' => [
                                'Nabídka dalšího produktu na stránce po odeslání objednávky',
                                'Naposledy prohlížené produkty',
                                'Časté otázky přímo u produktu',
                                'Faktury a dodací listy v barvách obchodu',
                                'Napojení na účetnictví Pohoda, ať se faktury nepřepisují ručně',
                                'Noční hlídání e-shopu, které ohlásí chybu dřív, než si jí všimnou zákazníci',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'cta',
                'data' => [
                    'eyebrow' => 'Pro váš e-shop',
                    'title' => 'Co z toho se hodí vám?',
                    'perex' => 'Projdeme váš e-shop a vybereme dvě tři věci, které dávají smysl právě u vás. Úvodní patnáctiminutový hovor je zdarma.',
                ],
            ],
        ];
    }

    /** @return array{type: string, data: array<string, mixed>} */
    private function tip(string $image, string $side, string $shop, string $title, string $text, string $alt): array
    {
        return [
            'type' => 'image_text',
            'data' => [
                'image' => Storage::disk('public')->exists($image) ? $image : null,
                'image_alt' => $alt,
                'side' => $side,
                'tone' => 'cream',
                'eyebrow' => $shop,
                'title' => $title,
                'body' => '<p>'.e($text).'</p>',
            ],
        ];
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
