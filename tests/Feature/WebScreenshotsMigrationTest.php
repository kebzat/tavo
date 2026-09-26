<?php

namespace Tests\Feature;

use App\Models\CaseStudy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Migrace, která referencím webů dává jednotné screenshoty. Staré obrázky
 * nesmí smazat, jen odložit, a opakované spuštění nesmí nic zdvojit.
 */
class WebScreenshotsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_09_25_190000_web_screenshots_in_case_studies.php';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function runMigration(): void
    {
        (require database_path(self::MIGRATION))->up();
    }

    private function case(string $slug, string $title): CaseStudy
    {
        return CaseStudy::create(['title' => $title, 'slug' => $slug, 'published' => true]);
    }

    public function test_nahradi_nahled_i_galerii_a_stare_obrazky_odlozi(): void
    {
        $case = $this->case('them-cars', 'THEM CARS');
        $case->addMedia(UploadedFile::fake()->image('notebook.jpg', 1200, 900))->toMediaCollection(CaseStudy::MEDIA_THUMB);
        $case->addMedia(UploadedFile::fake()->image('foto.jpg', 1200, 900))->toMediaCollection(CaseStudy::MEDIA_GALLERY);

        $this->runMigration();
        $case->refresh();

        $this->assertSame('them-cars.jpg', $case->getFirstMedia(CaseStudy::MEDIA_THUMB)->file_name);
        $this->assertSame(['them-cars.jpg'], $case->getMedia(CaseStudy::MEDIA_GALLERY)->pluck('file_name')->all());
        $this->assertSame('Úvodní stránka webu THEM CARS', $case->imageAlt());
        $this->assertEqualsCanonicalizing(['notebook.jpg', 'foto.jpg'], $case->getMedia('archiv')->pluck('file_name')->all());
    }

    public function test_opakovane_spusteni_nic_nezdvoji(): void
    {
        $case = $this->case('them-cars', 'THEM CARS');

        $this->runMigration();
        $this->runMigration();

        $this->assertCount(1, $case->refresh()->getMedia(CaseStudy::MEDIA_GALLERY));
        $this->assertCount(0, $case->getMedia('archiv'));
    }

    public function test_reference_mimo_seznam_zustane_beze_zmeny(): void
    {
        $case = $this->case('reklamni-grafika', 'Reklamní grafika');
        $case->addMedia(UploadedFile::fake()->image('kolaz.jpg', 1200, 900))->toMediaCollection(CaseStudy::MEDIA_THUMB);

        $this->runMigration();

        $this->assertSame('kolaz.jpg', $case->refresh()->getFirstMedia(CaseStudy::MEDIA_THUMB)->file_name);
    }

    public function test_2e_dostane_blok_pred_a_po_nad_ostatni_bloky(): void
    {
        $case = $this->case('2e-kompresory', '2e Kompresory');
        $case->blocks = [['type' => 'text', 'data' => ['body' => '<p>Původní blok</p>']]];
        $case->saveQuietly();

        $this->runMigration();
        $this->runMigration();

        $blocks = $case->refresh()->blocks;
        $this->assertCount(2, $blocks);
        $this->assertSame('before_after', $blocks[0]['type']);
        $this->assertSame('text', $blocks[1]['type']);
        Storage::disk('public')->assertExists(['reference/2e-kompresory-pred.jpg', 'reference/2e-kompresory-po.jpg']);

        $this->get('/reference/2e-kompresory')
            ->assertOk()
            ->assertSee('tavoBeforeAfter', false)
            ->assertSee('Starý e-shop vedle nového');
    }

    public function test_rappa_dostane_do_prazdne_galerie_svuj_nahled(): void
    {
        $case = $this->case('rappa', 'RAPPA');
        $case->addMedia(UploadedFile::fake()->image('rappa.png', 1600, 1000))->toMediaCollection(CaseStudy::MEDIA_THUMB);

        $migration = require database_path('migrations/2026_09_26_090000_rappa_gallery_from_thumb.php');
        $migration->up();
        $migration->up();

        $this->assertSame(['rappa.png'], $case->refresh()->getMedia(CaseStudy::MEDIA_GALLERY)->pluck('file_name')->all());
        $this->assertSame('rappa.png', $case->getFirstMedia(CaseStudy::MEDIA_THUMB)->file_name);
    }
}
