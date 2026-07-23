<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Komponenta <x-media> má dva režimy ořezu. „cover" drží pevný poměr, aby výpisy
 * zůstaly zarovnané; „natural" nechá obrázku vlastní poměr, aby se snímek webu
 * na detailu reference neuřízl. Zástupný vizuál potřebuje pevný poměr vždy,
 * jinak by měl nulovou výšku.
 */
class MediaComponentTest extends TestCase
{
    public function test_cover_orizne_obrazek_na_dany_pomer(): void
    {
        $html = Blade::render(
            '<x-media url="/obrazek.jpg" alt="Popis" ratio="aspect-[16/8]" radius="rounded-card" />'
        );

        $this->assertStringContainsString('aspect-[16/8]', $html);
        $this->assertStringContainsString('object-cover', $html);
    }

    public function test_natural_necha_obrazku_vlastni_pomer(): void
    {
        $html = Blade::render(
            '<x-media url="/obrazek.jpg" alt="Popis" ratio="aspect-[16/8]" radius="rounded-card" fit="natural" />'
        );

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
            $html = Blade::render(
                '<x-media url="/obrazek.jpg" alt="Popis" radius="rounded-card" fit="'.$fit.'" />'
            );

            $this->assertStringContainsString('rounded-card', $html, "Režim {$fit} přišel o zaoblení.");
            $this->assertStringContainsString('overflow-hidden', $html, "Režim {$fit} nemá ořez podle rámečku.");
        }
    }
}
