<?php

namespace Tests\Feature;

use App\Models\CaseStudy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Galerie na detailu reference je nepovinná: 0, 1 nebo víc obrázků. Prázdná galerie
 * se na webu nesmí vůbec vykreslit.
 */
class CaseStudyGalleryTest extends TestCase
{
    use RefreshDatabase;

    private function case(array $attributes = []): CaseStudy
    {
        return CaseStudy::create($attributes + [
            'title' => 'Testovací reference',
            'slug' => 'testovaci-reference',
            'published' => true,
        ]);
    }

    private function addGalleryImage(CaseStudy $case, string $name, string $alt): void
    {
        $case->addMedia(UploadedFile::fake()->image($name, 1200, 800))
            ->withCustomProperties(['alt' => $alt])
            ->toMediaCollection(CaseStudy::MEDIA_GALLERY);
    }

    public function test_bez_obrazku_se_galerie_nevykresli(): void
    {
        $this->case();

        // Slider vykresluje obrázky přes Alpine, ale komponenta se do stránky
        // vloží jen když galerie není prázdná.
        $this->get('/reference/testovaci-reference')
            ->assertOk()
            ->assertDontSee('tavoGallery', false);
    }

    public function test_jeden_obrazek_se_zobrazi(): void
    {
        Storage::fake('public');
        $case = $this->case();
        $this->addGalleryImage($case, 'ukazka.jpg', 'Náhled webu');

        // Data slideru (včetně alt textu) jsou v x-data komponenty.
        $this->get('/reference/testovaci-reference')
            ->assertOk()
            ->assertSee('tavoGallery', false)
            ->assertSee('Náhled webu', false);
    }

    public function test_vice_obrazku_zachova_poradi(): void
    {
        Storage::fake('public');
        $case = $this->case();
        $this->addGalleryImage($case, 'prvni.jpg', 'První ukázka');
        $this->addGalleryImage($case, 'druha.jpg', 'Druhá ukázka');
        $this->addGalleryImage($case, 'treti.jpg', 'Třetí ukázka');

        $images = $case->refresh()->galleryImages();

        $this->assertCount(3, $images);
        $this->assertSame('První ukázka', $images->first()['alt']);
        $this->assertSame('Třetí ukázka', $images->last()['alt']);

        // Vykresluje se zmenšenina, ne originál — poměr stran ale musí sedět,
        // jinak by prohlížeč rezervoval špatnou výšku a stránka by poskočila.
        $first = $images->first();
        $this->assertStringEndsWith('.webp', $first['src']);
        $this->assertSame(
            round(1200 / 800, 2),
            round($first['width'] / $first['height'], 2),
        );
        $this->assertStringContainsString('w,', (string) $first['srcset']);
    }

    public function test_chybejici_alt_ma_rozumny_zalozni_text(): void
    {
        Storage::fake('public');
        $case = $this->case();
        $case->addMedia(UploadedFile::fake()->image('bezpopisku.jpg', 800, 600))
            ->toMediaCollection(CaseStudy::MEDIA_GALLERY);

        $this->assertSame(
            'Testovací reference — ukázka 1',
            $case->refresh()->galleryImages()->first()['alt'],
        );
    }
}
