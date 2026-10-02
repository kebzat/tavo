<?php

namespace Tests\Feature;

use App\Models\CaseStudy;
use App\Models\CaseStudyCategory;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Blok „Před a po: víc obrazovek" a migrace, která ho připraví do referencí.
 * Hlavní pravidlo: záložka bez obou obrázků se nevysází a migrace nesahá
 * na existující bloky.
 */
class BeforeAfterTabsTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_10_02_100000_add_before_after_tabs_to_case_studies.php';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_vysazi_jen_obrazovky_s_obema_obrazky_v_poradi(): void
    {
        $this->image('a.jpg');
        $this->image('b.jpg');

        $this->page([
            ['type' => 'before_after_tabs', 'data' => [
                'title' => 'Uvnitř e-shopu',
                'screens' => [
                    ['label' => 'Kategorie', 'before' => 'a.jpg', 'after' => 'b.jpg', 'text' => 'Rozcestník podle chuti.'],
                    ['label' => 'Bez obrázku před', 'before' => null, 'after' => 'b.jpg'],
                    ['label' => 'Košík', 'before' => 'a.jpg', 'after' => 'b.jpg'],
                ],
            ]],
        ]);

        $this->get('/zkusebni')
            ->assertOk()
            ->assertSee('Uvnitř e-shopu')
            ->assertSeeInOrder(['Kategorie', 'Košík'])
            ->assertSee('Rozcestník podle chuti.')
            ->assertDontSee('Bez obrázku před');
    }

    public function test_bez_hotove_obrazovky_se_sekce_nevysazi(): void
    {
        $this->page([
            ['type' => 'before_after_tabs', 'data' => [
                'title' => 'Prázdné před a po',
                'screens' => [['label' => 'Kategorie', 'before' => null, 'after' => null]],
            ]],
        ]);

        $this->get('/zkusebni')->assertOk()->assertDontSee('Prázdné před a po');
    }

    public function test_jednoduche_opakovace_zustanou_retezce(): void
    {
        $this->page([['type' => 'pills', 'data' => ['title' => 'Platformy', 'items' => ['Shoptet', 'Upgates']]]]);

        $this->get('/zkusebni')->assertOk()->assertSee('Shoptet')->assertSee('Upgates');
    }

    public function test_karty_s_ukazkou_vysazi_co_dela_a_proc(): void
    {
        $this->page([
            ['type' => 'feature_cards', 'data' => [
                'title' => 'Co umíme',
                'items' => [
                    ['title' => 'Počítadlo do cíle', 'tag' => 'Svět Cejlonu', 'what' => 'Ukazuje vybranou částku.', 'why' => 'Zákazník vidí, k čemu přispěl.', 'link_url' => '/reference/svet-cejlonu'],
                    ['title' => '', 'what' => 'Karta bez nadpisu se nevysází.'],
                ],
            ]],
        ]);

        $this->get('/zkusebni')
            ->assertOk()
            ->assertSeeInOrder(['Počítadlo do cíle', 'Co to dělá', 'Ukazuje vybranou částku.', 'Proč se to vyplatí', 'Zákazník vidí, k čemu přispěl.'])
            ->assertSee('href="/reference/svet-cejlonu"', false)
            ->assertDontSee('Karta bez nadpisu se nevysází.');
    }

    public function test_migrace_pripravi_zalozky_podle_typu_reference_a_nic_neprepise(): void
    {
        $eshop = $this->caseStudy('eshop-zkouska', 'eshopy', [
            ['type' => 'before_after', 'data' => ['title' => 'Úvodní stránka před a po']],
            ['type' => 'text', 'data' => ['body' => '<p>Původní text.</p>']],
        ]);
        $web = $this->caseStudy('web-zkouska', 'weby', [['type' => 'text', 'data' => ['body' => '<p>Web.</p>']]]);
        $ads = $this->caseStudy('reklama-zkouska', 'reklama', [['type' => 'text', 'data' => ['body' => '<p>Reklama.</p>']]]);

        $migration = require database_path(self::MIGRATION);
        $migration->up();
        $migration->up();

        $eshopBlocks = $eshop->fresh()->blocks;
        $this->assertSame(['before_after', 'before_after_tabs', 'text'], array_column($eshopBlocks, 'type'));
        $this->assertSame('Úvodní stránka před a po', $eshopBlocks[0]['data']['title']);
        $this->assertSame('<p>Původní text.</p>', $eshopBlocks[2]['data']['body']);
        // Úvodní stránku už porovnává samostatný blok.
        $this->assertSame(['Kategorie', 'Detail produktu', 'Košík'], array_column($eshopBlocks[1]['data']['screens'], 'label'));

        $webBlocks = $web->fresh()->blocks;
        $this->assertSame(['text', 'before_after_tabs'], array_column($webBlocks, 'type'));
        $this->assertSame(['Úvodní stránka', 'Podstránka'], array_column($webBlocks[1]['data']['screens'], 'label'));

        $this->assertSame(['text'], array_column($ads->fresh()->blocks, 'type'));

        // Prázdné záložky na webu nic nezobrazí.
        $this->get('/reference/eshop-zkouska')->assertOk()->assertDontSee('Detail produktu');
    }

    private function image(string $path): void
    {
        Storage::disk('public')->put($path, UploadedFile::fake()->image($path, 1600, 1000)->getContent());
    }

    /** @param array<int, array<string, mixed>> $blocks */
    private function page(array $blocks): Page
    {
        return Page::create(['title' => 'Zkušební', 'slug' => 'zkusebni', 'published' => true, 'blocks' => $blocks]);
    }

    /** @param array<int, array<string, mixed>> $blocks */
    private function caseStudy(string $slug, string $category, array $blocks): CaseStudy
    {
        $category = CaseStudyCategory::firstOrCreate(['slug' => $category], ['name' => ucfirst($category)]);

        return CaseStudy::create([
            'title' => $slug,
            'slug' => $slug,
            'published' => true,
            'case_study_category_id' => $category->id,
            'blocks' => $blocks,
        ]);
    }
}
