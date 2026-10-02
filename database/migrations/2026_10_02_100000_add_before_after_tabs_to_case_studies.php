<?php

use App\Support\ResponsiveImage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Každá reference webu a e-shopu dostane blok „Před a po: víc obrazovek"
 * s prázdnými záložkami, ať stačí v administraci nahrát obrázky.
 * Záložka bez obou obrázků se nezobrazí, takže na webu se nic nezmění,
 * dokud správce obrázky nedoplní.
 *
 * Jen přidává. Existující bloky ani jiná pole reference nemění a reference,
 * která už takový blok má, se přeskočí. Reklamní reference (kreativy, videa)
 * blok nedostanou, obrazovky webu u nich nedávají smysl.
 *
 * Svět Cejlonu má rovnou nahrané snímky „po" z dnešního svetcejlonu.cz
 * (kategorie, detail produktu, košík; 1600 × 1000 od horního okraje).
 * Snímky „před" chybí, starý e-shop není nikde archivovaný.
 */
return new class extends Migration
{
    private const TYPE = 'before_after_tabs';

    /** Soubor v repozitáři → cesta na disku `public`. */
    private const CEJLON_IMAGES = [
        'po-kategorie.jpg' => 'reference/svet-cejlonu-po-kategorie.jpg',
        'po-detail.jpg' => 'reference/svet-cejlonu-po-detail.jpg',
        'po-kosik.jpg' => 'reference/svet-cejlonu-po-kosik.jpg',
    ];

    public function up(): void
    {
        $cases = DB::table('case_studies')
            ->leftJoin('case_study_categories', 'case_study_categories.id', '=', 'case_studies.case_study_category_id')
            ->select('case_studies.id', 'case_studies.slug', 'case_studies.blocks', 'case_study_categories.slug as category')
            ->get();

        foreach ($cases as $case) {
            $blocks = json_decode((string) $case->blocks, true) ?: [];

            if (collect($blocks)->contains(fn ($block) => ($block['type'] ?? null) === self::TYPE)) {
                continue;
            }

            $block = $case->slug === 'svet-cejlonu'
                ? $this->cejlonBlock()
                : $this->emptyBlock($case->category, $blocks);

            if (! $block) {
                continue;
            }

            // Hned za první „před a po", pokud reference nějaké má (navazuje
            // na něj), jinak na konec obsahu.
            $after = collect($blocks)->search(fn ($existing) => ($existing['type'] ?? null) === 'before_after');
            array_splice($blocks, $after === false ? count($blocks) : $after + 1, 0, [$block]);

            DB::table('case_studies')->where('id', $case->id)->update([
                'blocks' => json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('case_studies')->select('id', 'blocks')->get() as $case) {
            $blocks = json_decode((string) $case->blocks, true) ?: [];
            $kept = array_values(array_filter($blocks, fn ($block) => ($block['type'] ?? null) !== self::TYPE));

            if (count($kept) !== count($blocks)) {
                DB::table('case_studies')->where('id', $case->id)->update([
                    'blocks' => json_encode($kept, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            }
        }
    }

    /**
     * Prázdné záložky podle typu reference. Úvodní stránka odpadá, když už ji
     * reference porovnává samostatným blokem.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<string, mixed>|null
     */
    private function emptyBlock(?string $category, array $blocks): ?array
    {
        $labels = match ($category) {
            'eshopy' => ['Úvodní stránka', 'Kategorie', 'Detail produktu', 'Košík'],
            'weby' => ['Úvodní stránka', 'Podstránka'],
            default => null,
        };

        if (! $labels) {
            return null;
        }

        if (collect($blocks)->contains(fn ($block) => ($block['type'] ?? null) === 'before_after')) {
            $labels = array_values(array_diff($labels, ['Úvodní stránka']));
        }

        return $this->block([
            'tone' => 'cream',
            'title' => 'Před a po',
        ], array_map(fn (string $label) => ['label' => $label], $labels));
    }

    /** @return array<string, mixed> */
    private function cejlonBlock(): array
    {
        $this->copyImages();

        return $this->block([
            'tone' => 'cream',
            'eyebrow' => 'Uvnitř e-shopu',
            'title' => 'Kategorie, detail produktu a košík',
        ], [
            [
                'label' => 'Kategorie',
                'after' => self::CEJLON_IMAGES['po-kategorie.jpg'],
                'after_alt' => 'Kategorie Čaje na novém e-shopu Svět Cejlonu s rozcestníkem podle chuti',
                'before_alt' => 'Kategorie čajů na původním e-shopu Svět Cejlonu',
                'text' => 'Nad výpisem je rozcestník podle chuti a denní doby: na energii, na večer, bez kofeinu. Kdo neví, který čaj chce, nemusí procházet celý výpis.',
            ],
            [
                'label' => 'Detail produktu',
                'after' => self::CEJLON_IMAGES['po-detail.jpg'],
                'after_alt' => 'Detail produktu Modrý čaj s variantami balení a tlačítkem do košíku',
                'before_alt' => 'Detail produktu na původním e-shopu Svět Cejlonu',
                'text' => 'Varianty balení jsou vedle sebe s cenou a počtem šálků. Tlačítko do košíku ukazuje rovnou cenu zvolené varianty.',
            ],
            [
                'label' => 'Košík',
                'after' => self::CEJLON_IMAGES['po-kosik.jpg'],
                'after_alt' => 'Nový košík s lištou do dopravy zdarma, dárky k objednávce a korunou pro školu',
                'before_alt' => 'Košík na původním e-shopu Svět Cejlonu',
                'text' => 'Košík prošel redesignem. Nahoře lišta, kolik chybí do dopravy zdarma, pod produkty dárky, na které zákazník dosáhne větším nákupem, a připomínka koruny pro školu na Srí Lance.',
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $screens
     * @return array<string, mixed>
     */
    private function block(array $data, array $screens): array
    {
        $empty = ['before' => null, 'after' => null, 'before_alt' => null, 'after_alt' => null, 'text' => null];

        return [
            'type' => self::TYPE,
            'data' => $data + [
                'eyebrow' => null,
                'perex' => null,
                // Seznam, ne objekt s klíči: JSON sloupec v MySQL by klíče
                // přeřadil a záložky by se zpřeházely.
                'screens' => array_map(fn (array $screen) => $screen + $empty, $screens),
            ],
        ];
    }

    private function copyImages(): void
    {
        foreach (self::CEJLON_IMAGES as $source => $target) {
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
};
