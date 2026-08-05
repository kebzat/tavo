<?php

namespace Tests\Feature;

use App\Models\CaseStudy;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Detail reference má pevný jen úvod (hlavička, údaje o projektu, zadání).
 * Všechno pod ním si správce skládá z bloků, stejných jako na statické stránce.
 */
class CaseStudyBlocksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
    }

    public function test_detail_vysadi_pevny_uvod_i_bloky(): void
    {
        $this->get('/reference/vcely-uhersko')
            ->assertOk()
            // Pevná část
            ->assertSee('Značka a web pro', false)
            ->assertSee('Zadání', false)
            ->assertSee('Včelařství a agroturistika', false)
            // Blok z databáze
            ->assertSee('Co jsme na projektu dělali', false)
            ->assertSee('Vizuální styl značky od loga po barvy a typografii.', false);
    }

    public function test_reference_bez_bloku_ma_porad_uvod_i_zaver(): void
    {
        CaseStudy::where('slug', 'vcely-uhersko')->update(['blocks' => []]);

        $this->get('/reference/vcely-uhersko')
            ->assertOk()
            ->assertSee('Zadání', false)
            ->assertSee('Další projekt', false)
            ->assertDontSee('Co jsme na projektu dělali', false);
    }

    public function test_do_reference_jde_pridat_kterykoliv_blok(): void
    {
        CaseStudy::where('slug', 'vcely-uhersko')->update(['blocks' => [
            ['type' => 'metrics', 'data' => [
                'tone' => 'brick',
                'title' => 'Výsledek',
                'items' => [['value' => '+41 %', 'label' => 'meziroční růst']],
            ]],
            ['type' => 'quote', 'data' => ['text' => 'Konečně to dává smysl.', 'author' => 'Jan Novák']],
        ]]);

        $this->get('/reference/vcely-uhersko')
            ->assertOk()
            ->assertSee('+41 %', false)
            ->assertSee('meziroční růst', false)
            ->assertSee('Konečně to dává smysl.', false)
            ->assertSee('Jan Novák', false);
    }

    public function test_obrazek_v_bloku_reference_dostane_zmenseninu_i_popisek(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('reference/foto.jpg', UploadedFile::fake()->image('foto.jpg', 1600, 900)->get());

        CaseStudy::where('slug', 'vcely-uhersko')->update(['blocks' => [
            ['type' => 'image', 'data' => ['image' => 'reference/foto.jpg', 'image_alt' => 'Popisek']],
        ]]);

        $this->get('/reference/vcely-uhersko')
            ->assertOk()
            // Návštěvník dostane WebP v šířce podle displeje, ne původní soubor.
            ->assertSee('/storage/zmenseniny/reference/foto-1440.webp', false)
            ->assertSee('srcset=', false)
            ->assertSee('alt="Popisek"', false);
    }

    public function test_blok_s_chybejicim_souborem_stranku_neshodi(): void
    {
        Storage::fake('public');

        CaseStudy::where('slug', 'vcely-uhersko')->update(['blocks' => [
            ['type' => 'image', 'data' => ['image' => 'reference/neexistuje.jpg', 'image_alt' => 'Popisek']],
        ]]);

        // Bez souboru se blok prostě vynechá — lepší než rozbitý obrázek.
        $this->get('/reference/vcely-uhersko')
            ->assertOk()
            ->assertDontSee('reference/neexistuje.jpg', false);
    }
}
