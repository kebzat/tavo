<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Komponenta <x-media> má dva režimy ořezu. „cover" drží pevný poměr, aby výpisy
 * zůstaly zarovnané; „natural" nechá obrázku vlastní poměr, aby se snímek webu
 * na detailu reference neuřízl. Zástupný vizuál potřebuje pevný poměr vždy,
 * jinak by měl nulovou výšku.
 *
 * Zároveň hlídáme, že se obrázek vysází s rozměry a sadou zmenšenin — bez nich
 * stránka při načítání poskakuje a mobil stahuje zbytečně velký soubor.
 */
class MediaComponentTest extends TestCase
{
    /** @return array{src: string, srcset: string, width: int, height: int, alt: string} */
    private function image(): array
    {
        return [
            'src' => '/storage/zmenseniny/obrazek-1440.webp',
            'srcset' => '/storage/zmenseniny/obrazek-768.webp 768w, /storage/zmenseniny/obrazek-1440.webp 1440w',
            'width' => 1440,
            'height' => 810,
            'alt' => 'Popis',
        ];
    }

    private function render(string $attributes): string
    {
        return Blade::render(
            '<x-media :image="$image" '.$attributes.' />',
            ['image' => $this->image()],
        );
    }

    public function test_cover_orizne_obrazek_na_dany_pomer(): void
    {
        $html = $this->render('ratio="aspect-[16/8]" radius="rounded-card"');

        $this->assertStringContainsString('aspect-[16/8]', $html);
        $this->assertStringContainsString('object-cover', $html);
    }

    public function test_natural_necha_obrazku_vlastni_pomer(): void
    {
        $html = $this->render('ratio="aspect-[16/8]" radius="rounded-card" fit="natural"');

        $this->assertStringNotContainsString('aspect-[16/8]', $html);
        $this->assertStringNotContainsString('object-cover', $html);
        $this->assertStringContainsString('h-auto w-full', $html);
    }

    public function test_zastupny_vizual_si_pomer_drzi_i_v_rezimu_natural(): void
    {
        $html = Blade::render(
            '<x-media label="Zatím bez fotky" ratio="aspect-[16/8]" radius="rounded-card" fit="natural" />'
        );

        $this->assertStringContainsString('aspect-[16/8]', $html);
        $this->assertStringContainsString('Zatím bez fotky', $html);
    }

    public function test_obrazek_je_v_kazdem_rezimu_orezany_zaoblenim_ramecku(): void
    {
        foreach (['cover', 'natural'] as $fit) {
            $html = $this->render('radius="rounded-card" fit="'.$fit.'"');

            $this->assertStringContainsString('rounded-card', $html, "Režim {$fit} přišel o zaoblení.");
            $this->assertStringContainsString('overflow-hidden', $html, "Režim {$fit} nemá ořez podle rámečku.");
        }
    }

    public function test_obrazek_dostane_rozmery_i_zmenseniny(): void
    {
        $html = $this->render('sizes="50vw"');

        $this->assertStringContainsString('width="1440"', $html);
        $this->assertStringContainsString('height="810"', $html);
        $this->assertStringContainsString('sizes="50vw"', $html);
        $this->assertStringContainsString('1440w', $html);
        $this->assertStringContainsString('alt="Popis"', $html);
    }

    public function test_obrazek_pod_prehybem_se_nacita_az_pri_scrollovani(): void
    {
        $this->assertStringContainsString('loading="lazy"', $this->render(''));
    }

    public function test_obrazek_na_prvni_obrazovce_se_nacita_prednostne(): void
    {
        $html = $this->render(':priority="true"');

        $this->assertStringContainsString('loading="eager"', $html);
        $this->assertStringContainsString('fetchpriority="high"', $html);
    }
}
