<?php

use App\Models\CaseStudy;
use App\Models\CaseStudyCategory;
use App\Support\ResponsiveImage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Reference Svět Cejlonu jako nejnovější projekt: první ve výpisu a ve
 * velkém pod úvodem homepage.
 *
 * Texty a obrázky vychází z tomaskebza.cz/reference/svet-cejlonu. Snímky
 * „před" jsou z podkladů klienta (původní web), snímek „po" z dnešního
 * svetcejlonu.cz, oba 1600 × 1000 od horního okraje stránky.
 *
 * Když reference se stejným slugem už existuje (založil ji mezitím někdo
 * v administraci), migrace nedělá nic. Na prázdné databázi taky ne, tam
 * obsah zakládá seeder.
 *
 * Pravidla pro psaní textů: .claude/skills/tavo-copy/SKILL.md
 */
return new class extends Migration
{
    private const SLUG = 'svet-cejlonu';

    /** Soubor v repozitáři → cesta na disku `public` (obrázky do bloků). */
    private const BLOCK_IMAGES = [
        'pred.jpg' => 'reference/svet-cejlonu-pred.jpg',
        'po.jpg' => 'reference/svet-cejlonu-po.jpg',
        'darkove-balicky.jpg' => 'reference/svet-cejlonu-darkove-balicky.jpg',
        'koruna-pro-skolu.jpg' => 'reference/svet-cejlonu-koruna-pro-skolu.jpg',
    ];

    public function up(): void
    {
        // Prázdná databáze (čistá instalace, testy) dostává obsah ze seederu.
        // Migrace je pro běžící web, který už reference má.
        if (CaseStudy::query()->doesntExist() || CaseStudy::query()->where('slug', self::SLUG)->exists()) {
            return;
        }

        $this->copyBlockImages();

        // Nejnovější projekt jde ve výpisu první, ostatní se posunou o jedno místo.
        CaseStudy::query()->increment('order_column');

        $case = CaseStudy::query()->create([
            'case_study_category_id' => CaseStudyCategory::query()->where('slug', 'eshopy')->value('id'),
            'title' => 'Svět Cejlonu',
            'slug' => self::SLUG,
            'published' => true,
            'is_featured' => false,
            'eyebrow' => 'E-shop · Redesign na Upgates',
            'excerpt' => 'E-shop s čaji, kořením a ájurvédou ze Srí Lanky. Nový vzhled na Upgates od úvodní stránky po košík, dárkové balíčky, které si zákazník poskládá sám, a počítadlo koruny pro tamilskou školu.',
            'tags' => ['Vlastní zakázka'],
            'hero_headline' => 'Čaj ze Srí Lanky a koruna,',
            'hero_headline_accent' => 'která se tam vrací.',
            'hero_perex' => 'Marek vozí čaje, koření a ájurvédu přímo ze Srí Lanky, kde nějaký čas žil. Jeho e-shop na Upgates dostal nový vzhled od úvodní stránky po košík. K tomu dvě funkce navíc: dárkový balíček, který si zákazník poskládá sám, a počítadlo koruny pro tamilskou školu.',
            'client' => 'Svět Cejlonu',
            'industry' => 'Čaj, koření a ájurvéda ze Srí Lanky',
            'scope' => 'Redesign e-shopu, konfigurátor dárkových balíčků, počítadlo příspěvků',
            'website_url' => 'https://www.svetcejlonu.cz/',
            'problem_title' => 'Zadání',
            'problem_text' => 'Svět Cejlonu stojí na příběhu. Člověk odletí na Srí Lanku s jednosměrnou letenkou a začne odtamtud posílat čaj domů. E-shop měl ten příběh unést a přitom zůstat obchodem, ve kterém se rychle nakupuje.',
            'problem_points' => [
                'Sortiment je široký: čaje, koření, doplňky stravy, ájurvéda. Kdo přijde pro jeden konkrétní čaj, nesmí se proklikávat vším.',
                'Čaj a koření jsou častý dárek, takže si zákazník měl umět balíček poskládat sám.',
                'Z každého prodaného produktu jde koruna škole na Srí Lance. Tohle nemělo zůstat schované v patičce.',
            ],
            'blocks' => $this->blocks(),
        ]);

        // Pořadí až po založení: řazení modelu si ho při vytváření nastavuje samo.
        $case->forceFill(['order_column' => 1])->saveQuietly();

        $screenshot = database_path('seeders/assets/svet-cejlonu/svet-cejlonu.jpg');

        if (File::isFile($screenshot)) {
            foreach ([CaseStudy::MEDIA_THUMB, CaseStudy::MEDIA_GALLERY] as $collection) {
                $media = $case->addMedia($screenshot)
                    ->preservingOriginal()
                    ->usingFileName('svet-cejlonu.jpg')
                    ->withCustomProperties(['alt' => 'Úvodní stránka e-shopu Svět Cejlonu'])
                    ->toMediaCollection($collection);

                ResponsiveImage::generate($media->getPathRelativeToRoot());
            }
        }

        $this->showOnHome($case);
    }

    public function down(): void
    {
        $case = CaseStudy::query()->where('slug', self::SLUG)->first();

        if (! $case) {
            return;
        }

        $row = DB::table('settings')->where('group', 'home')->where('name', 'latest_case_id')->first();

        if ($row && json_decode((string) $row->payload) === $case->id) {
            DB::table('settings')->where('id', $row->id)->update(['payload' => json_encode(null)]);
            Artisan::call('settings:clear-cache');
        }

        $case->delete();
        CaseStudy::query()->decrement('order_column');

        // Obrázky bloků na disku necháváme. Mazat soubory kvůli rollbacku
        // by bylo víc škody než užitku.
    }

    private function copyBlockImages(): void
    {
        foreach (self::BLOCK_IMAGES as $source => $target) {
            $path = database_path("seeders/assets/svet-cejlonu/{$source}");

            if (! File::isFile($path)) {
                continue;
            }

            if (! Storage::disk('public')->exists($target)) {
                Storage::disk('public')->put($target, File::get($path));
            }

            ResponsiveImage::generate($target);
        }
    }

    /**
     * Zapíše referenci do „Nejnovějšího projektu" na homepage. Jen když tam
     * správce zatím nic nevybral.
     */
    private function showOnHome(CaseStudy $case): void
    {
        $row = DB::table('settings')->where('group', 'home')->where('name', 'latest_case_id')->first();

        if (! $row || json_decode((string) $row->payload) !== null) {
            return;
        }

        DB::table('settings')->where('id', $row->id)->update(['payload' => json_encode($case->id)]);
        Artisan::call('settings:clear-cache');
    }

    /**
     * Před a po hned pod zadáním, pak obě funkce, které e-shop dostal navíc.
     * Barvy se střídají, ať na sebe nenavazují dva tmavé pruhy (za bloky jde
     * tmavý „Další projekt").
     *
     * @return array<int, array{type: string, data: array<string, mixed>}>
     */
    private function blocks(): array
    {
        return [
            [
                'type' => 'before_after',
                'data' => [
                    'tone' => 'cream',
                    'eyebrow' => 'Redesign',
                    'title' => 'Úvodní stránka před a po',
                    'perex' => 'Vlevo původní e-shop, vpravo ten dnešní. Tažením čáry porovnáte, jak se změnila první obrazovka.',
                    'before' => self::BLOCK_IMAGES['pred.jpg'],
                    'after' => self::BLOCK_IMAGES['po.jpg'],
                    'before_alt' => 'Původní úvodní stránka e-shopu Svět Cejlonu',
                    'after_alt' => 'Nová úvodní stránka e-shopu Svět Cejlonu na Upgates',
                    'before_label' => 'Před',
                    'after_label' => 'Po',
                ],
            ],
            [
                'type' => 'image_text',
                'data' => [
                    'tone' => 'ink',
                    'side' => 'left',
                    'image' => self::BLOCK_IMAGES['darkove-balicky.jpg'],
                    'image_alt' => 'Konfigurátor dárkového balíčku: krabice s čaji a kořením a seznam položek',
                    'eyebrow' => 'Dárkové balíčky',
                    'title' => 'Krabice, která hlídá, kolik se do ní vejde',
                    'body' => '<p>Zákazník si vybere malou, nebo velkou krabici a skládá do ní čaje, koření, bylinky a doplňky. Položky vidí rovnou v krabici a web mu hlídá místo. Co se do malé nevejde, nabídne do velké.</p><p>Kdo nechce vybírat od nuly, sáhne po hotovém balíčku a jen ho upraví. Do košíku pak jde najednou celý balíček i s krabicí.</p>',
                ],
            ],
            [
                'type' => 'image_text',
                'data' => [
                    'tone' => 'cream',
                    'side' => 'right',
                    'image' => self::BLOCK_IMAGES['koruna-pro-skolu.jpg'],
                    'image_alt' => 'Počítadlo na úvodní stránce: kolik korun se vybralo pro školu Thennakumbura',
                    'eyebrow' => '1 Kč za každý produkt',
                    'title' => 'Koruna pro školu, kterou nikdo nepočítá ručně',
                    'body' => '<p>Z každého produktu v objednávce jde 1 Kč tamilské škole Thennakumbura v horách nad Ellou. Částka se připisuje automaticky s každou objednávkou, takže ji nikdo nemusí dohledávat v tabulce.</p><p>Na webu je vidět, kolik se už vybralo a kolik chybí do cíle. Za vybrané peníze se pak pořídí to, co škola zrovna potřebuje: sešity, tabule nebo stavební materiál.</p>',
                ],
            ],
        ];
    }
};
