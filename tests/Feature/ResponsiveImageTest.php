<?php

namespace Tests\Feature;

use App\Support\ResponsiveImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Do administrace se nahrávají originály z fotoaparátu. Web z nich musí udělat
 * úzké WebP varianty — jinak by návštěvník na mobilu stahoval dvoumegový PNG.
 */
class ResponsiveImageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function ulozObrazek(string $path, int $width, int $height, string $extension = 'jpg'): void
    {
        Storage::disk('public')->put(
            $path,
            UploadedFile::fake()->image('zdroj.'.$extension, $width, $height)->get(),
        );
    }

    public function test_siroky_obrazek_dostane_sadu_variant_ve_webp(): void
    {
        $this->ulozObrazek('foto/velky.jpg', 3000, 2000);

        $image = ResponsiveImage::make('foto/velky.jpg', 'Popisek');

        $this->assertNotNull($image);
        $this->assertSame('Popisek', $image['alt']);
        $this->assertStringEndsWith('/storage/zmenseniny/foto/velky-1920.webp', $image['src']);

        foreach ([480, 768, 1024, 1440, 1920] as $width) {
            $this->assertStringContainsString($width.'w', (string) $image['srcset']);
            Storage::disk('public')->assertExists("zmenseniny/foto/velky-{$width}.webp");
        }
    }

    public function test_uzky_obrazek_se_nezvetsuje(): void
    {
        $this->ulozObrazek('foto/maly.jpg', 600, 400);

        $image = ResponsiveImage::make('foto/maly.jpg');

        // 480 se vejde, širší varianty by byly rozmazané.
        $this->assertStringContainsString('480w', (string) $image['srcset']);
        $this->assertStringNotContainsString('1024w', (string) $image['srcset']);
        Storage::disk('public')->assertMissing('zmenseniny/foto/maly-1024.webp');
    }

    public function test_rozmery_odpovidaji_poslanemu_souboru(): void
    {
        $this->ulozObrazek('foto/pomer.jpg', 2000, 1000);

        $image = ResponsiveImage::make('foto/pomer.jpg');

        // Poměr stran musí sedět, jinak prohlížeč rezervuje špatnou výšku.
        $this->assertSame(2.0, round($image['width'] / $image['height'], 2));
    }

    public function test_svg_projde_beze_zmeny(): void
    {
        Storage::disk('public')->put('ikony/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

        $image = ResponsiveImage::make('ikony/logo.svg', 'Logo');

        $this->assertStringEndsWith('/storage/ikony/logo.svg', $image['src']);
        $this->assertNull($image['srcset']);
    }

    public function test_chybejici_soubor_vrati_null(): void
    {
        $this->assertNull(ResponsiveImage::make('foto/neexistuje.jpg'));
        $this->assertNull(ResponsiveImage::make(null));
        $this->assertNull(ResponsiveImage::make(''));
    }

    public function test_cesta_ven_z_disku_se_neprojde(): void
    {
        $this->assertNull(ResponsiveImage::normalize('../../.env'));
        $this->assertNull(ResponsiveImage::make('foto/../../../.env'));
    }

    public function test_hotova_url_se_prevede_na_cestu_na_disku(): void
    {
        $this->assertSame('foto/velky.jpg', ResponsiveImage::normalize(url('/storage/foto/velky.jpg')));
        $this->assertSame('foto/velky.jpg', ResponsiveImage::normalize('/storage/foto/velky.jpg'));
        $this->assertSame('foto/velky.jpg', ResponsiveImage::normalize('foto/velky.jpg'));
    }

    public function test_varianty_se_podruhe_negeneruji_znovu(): void
    {
        $this->ulozObrazek('foto/opakovane.jpg', 1600, 900);

        ResponsiveImage::make('foto/opakovane.jpg');
        $first = Storage::disk('public')->lastModified('zmenseniny/foto/opakovane-1440.webp');

        ResponsiveImage::make('foto/opakovane.jpg');

        $this->assertSame(
            $first,
            Storage::disk('public')->lastModified('zmenseniny/foto/opakovane-1440.webp'),
        );
    }
}
