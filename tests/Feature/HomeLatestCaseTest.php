<?php

namespace Tests\Feature;

use App\Models\CaseStudy;
use App\Settings\HomeSettings;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Sekce „Nejnovější projekt" pod úvodem homepage a migrace, která do ní
 * dává Svět Cejlonu.
 */
class HomeLatestCaseTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_09_26_120000_add_svet_cejlonu_case_study.php';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(ContentSeeder::class);
    }

    private function setLatest(?int $id): void
    {
        $settings = app(HomeSettings::class);
        $settings->latest_case_id = $id;
        $settings->save();
        app()->forgetInstance(HomeSettings::class);
    }

    public function test_migrace_zalozi_svet_cejlonu_jako_nejnovejsi_projekt(): void
    {
        CaseStudy::create(['title' => 'Starší projekt', 'slug' => 'starsi', 'published' => true, 'order_column' => 1]);

        (require database_path(self::MIGRATION))->up();
        (require database_path(self::MIGRATION))->up();
        app()->forgetInstance(HomeSettings::class);

        $case = CaseStudy::where('slug', 'svet-cejlonu')->sole();
        $this->assertSame($case->id, app(HomeSettings::class)->latest_case_id);
        $this->assertSame('svet-cejlonu', CaseStudy::ordered()->first()->slug);
        $this->assertSame(2, CaseStudy::where('slug', 'starsi')->value('order_column'));
        $this->assertNotNull($case->beforeAfter());

        $this->get('/')
            ->assertOk()
            ->assertSee('id="nejnovejsi-projekt"', false)
            ->assertSee('tavoBeforeAfter', false)
            ->assertSee('Svět Cejlonu');

        $this->get('/reference/svet-cejlonu')
            ->assertOk()
            ->assertSee('Úvodní stránka před a po')
            ->assertSee('Krabice, která hlídá, kolik se do ní vejde')
            ->assertSee('href="https://www.svetcejlonu.cz/"', false);
    }

    public function test_bez_vybrane_reference_se_sekce_nezobrazi(): void
    {
        $this->setLatest(null);

        $this->get('/')->assertOk()->assertDontSee('id="nejnovejsi-projekt"', false);
    }

    public function test_reference_bez_pred_a_po_ukaze_obrazek(): void
    {
        $case = CaseStudy::create(['title' => 'Jiný projekt', 'slug' => 'jiny-projekt', 'published' => true]);
        $case->addMedia(UploadedFile::fake()->image('web.jpg', 1920, 1200))->toMediaCollection(CaseStudy::MEDIA_GALLERY);
        $this->setLatest($case->id);

        $this->get('/')
            ->assertOk()
            ->assertSee('id="nejnovejsi-projekt"', false)
            ->assertSee('Jiný projekt')
            ->assertDontSee('tavoBeforeAfter', false);
    }

    public function test_nezverejnena_reference_se_neukaze(): void
    {
        $case = CaseStudy::create(['title' => 'Koncept', 'slug' => 'koncept', 'published' => false]);
        $case->addMedia(UploadedFile::fake()->image('web.jpg', 1920, 1200))->toMediaCollection(CaseStudy::MEDIA_GALLERY);
        $this->setLatest($case->id);

        $this->get('/')->assertOk()->assertDontSee('id="nejnovejsi-projekt"', false);
    }
}
