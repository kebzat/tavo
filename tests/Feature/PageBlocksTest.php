<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Statická stránka se skládá z bloků. Hlídáme, že se každý typ vykreslí,
 * že prázdný blok po sobě nenechá prázdné místo a že obrázek dostane URL.
 */
class PageBlocksTest extends TestCase
{
    use RefreshDatabase;

    public function test_stranka_vykresli_vsechny_typy_bloku(): void
    {
        $this->makePage([
            ['type' => 'text', 'data' => ['body' => '<p>Text z editoru.</p>']],
            ['type' => 'image_text', 'data' => ['title' => 'Vedle sebe', 'body' => '<p>Popis.</p>']],
            ['type' => 'metrics', 'data' => ['title' => 'Čísla', 'items' => [['value' => '+41 %', 'label' => 'růst']]]],
            ['type' => 'points', 'data' => ['title' => 'Body', 'items' => ['První bod']]],
            ['type' => 'steps', 'data' => ['title' => 'Postup', 'items' => [['title' => 'Projdeme e-shop', 'text' => 'Popis kroku.']]]],
            ['type' => 'cards', 'data' => ['title' => 'Karty', 'items' => [['title' => 'Analýza', 'text' => 'Popis karty.']]]],
            ['type' => 'pills', 'data' => ['title' => 'Platformy', 'items' => ['Shoptet', 'Upgates']]],
            ['type' => 'quote', 'data' => ['text' => 'Citace klienta.', 'author' => 'Jan Novák']],
            ['type' => 'cta', 'data' => ['title' => 'Ozvěte se nám.']],
        ]);

        $this->get('/zkusebni')
            ->assertOk()
            ->assertSee('Text z editoru.', false)
            ->assertSee('Vedle sebe', false)
            ->assertSee('+41 %', false)
            ->assertSee('První bod', false)
            ->assertSee('Projdeme e-shop', false)
            ->assertSee('Analýza', false)
            ->assertSee('Shoptet', false)
            ->assertSee('Citace klienta.', false)
            ->assertSee('Ozvěte se nám.', false);
    }

    public function test_odrazky_ve_sloupcich_vysadi_nadtitulek_i_poznamku(): void
    {
        $this->makePage([
            ['type' => 'bullets', 'data' => [
                'tone' => 'ink',
                'title' => 'Co jsme dělali',
                'columns' => [
                    ['label' => 'Role marketingu', 'items' => ['První bod', '', 'Druhý bod']],
                    ['label' => 'Role vývoje', 'items' => ['Třetí bod']],
                ],
                'note' => 'Čísla nemáme ověřená.',
            ]],
        ]);

        $this->get('/zkusebni')
            ->assertOk()
            ->assertSee('Co jsme dělali', false)
            ->assertSee('Role marketingu', false)
            ->assertSee('Role vývoje', false)
            ->assertSee('První bod', false)
            ->assertSee('Třetí bod', false)
            ->assertSee('Čísla nemáme ověřená.', false)
            // Dva sloupce se dělí na půl, jeden by zabral celou šířku.
            ->assertSee('menu:grid-cols-2', false);
    }

    public function test_sloupec_bez_odrazek_se_zahodi(): void
    {
        $this->makePage([
            ['type' => 'bullets', 'data' => [
                'columns' => [
                    ['label' => 'Má odrážky', 'items' => ['Bod']],
                    ['label' => 'Prázdný sloupec', 'items' => []],
                ],
            ]],
        ]);

        $this->get('/zkusebni')
            ->assertOk()
            ->assertSee('Má odrážky', false)
            ->assertDontSee('Prázdný sloupec', false)
            ->assertDontSee('menu:grid-cols-2', false);
    }

    public function test_statistiky_v_cihlove_maji_tmavy_text(): void
    {
        $this->makePage([
            ['type' => 'metrics', 'data' => [
                'tone' => 'brick',
                'title' => 'Výsledek',
                'items' => [['value' => '+41 %', 'label' => 'růst']],
            ]],
        ]);

        // Na cihlové by cihlové číslo zaniklo, musí být tmavé.
        $this->get('/zkusebni')
            ->assertOk()
            ->assertSee('data-block-bg="brick"', false)
            ->assertSee('bg-brick text-ink', false)
            ->assertDontSee('text-metric-lg font-extrabold tracking-[-.03em] text-brick', false);
    }

    public function test_porovnani_pred_a_po_vysadi_oba_obrazky(): void
    {
        $this->makePage([
            ['type' => 'before_after', 'data' => [
                'title' => 'Přetáhněte čáru',
                'before' => 'pages/pred.jpg',
                'after' => 'pages/po.jpg',
                'before_alt' => 'Původní stav',
                'after_alt' => 'Nový stav',
            ]],
        ]);

        $this->get('/zkusebni')
            ->assertOk()
            ->assertSee('Přetáhněte čáru', false)
            ->assertSee('/storage/pages/pred.jpg', false)
            ->assertSee('/storage/pages/po.jpg', false)
            ->assertSee('alt="Původní stav"', false)
            // Bez myši musí jít čára posunout klávesnicí.
            ->assertSee('aria-label="Porovnání stavu Před a Po"', false);
    }

    public function test_porovnani_bez_obou_obrazku_se_nevykresli(): void
    {
        $this->makePage([
            ['type' => 'before_after', 'data' => ['title' => 'Jen před', 'before' => 'pages/pred.jpg']],
        ]);

        $this->get('/zkusebni')
            ->assertOk()
            ->assertDontSee('Jen před', false)
            ->assertDontSee('/storage/pages/pred.jpg', false);
    }

    public function test_zastupny_vizual_drzi_misto_dokud_neni_nahrana_fotka(): void
    {
        $this->makePage([
            ['type' => 'image_text', 'data' => ['title' => 'Sekce', 'image_label' => 'Sem přijde ukázka']],
        ]);

        $this->get('/zkusebni')
            ->assertOk()
            ->assertSee('Sem přijde ukázka', false)
            ->assertSee('hatch-light', false);
    }

    public function test_bez_popisku_zastupneho_vizualu_je_sekce_jen_text(): void
    {
        $this->makePage([
            ['type' => 'image_text', 'data' => ['title' => 'Sekce bez vizuálu']],
        ]);

        $this->get('/zkusebni')
            ->assertOk()
            ->assertSee('Sekce bez vizuálu', false)
            ->assertDontSee('hatch-light', false);
    }

    public function test_hlavicka_zvyrazni_cast_nadpisu(): void
    {
        $page = Page::create([
            'title' => 'Předěláme e-shop, který nevydělává',
            'slug' => 'zkusebni',
            'published' => true,
            'hero_eyebrow' => 'Pro majitele e-shopů',
            'hero_accent' => 'nevydělává',
            'hero_cta' => true,
            'blocks' => [],
        ]);

        $this->assertSame(
            ['before' => 'Předěláme e-shop, který ', 'accent' => 'nevydělává', 'after' => ''],
            $page->headlineParts(),
        );

        $this->get('/zkusebni')
            ->assertOk()
            ->assertSee('Pro majitele e-shopů', false)
            ->assertSee('<span class="text-brick italic">nevydělává</span>', false)
            ->assertSee('Zavolat', false);
    }

    public function test_nenalezeny_zvyraznovany_vyraz_nadpis_nerozbije(): void
    {
        $page = Page::create([
            'title' => 'Ochrana osobních údajů',
            'slug' => 'zkusebni',
            'published' => true,
            'hero_accent' => 'tohle tam není',
            'blocks' => [],
        ]);

        $this->assertSame(
            ['before' => 'Ochrana osobních údajů', 'accent' => null, 'after' => ''],
            $page->headlineParts(),
        );

        $this->get('/zkusebni')
            ->assertOk()
            ->assertSee('Ochrana osobních údajů', false)
            ->assertDontSee('text-brick italic', false);
    }

    public function test_ocislovane_body_maji_souvisle_cislovani(): void
    {
        $this->makePage([
            ['type' => 'points', 'data' => ['items' => ['První', '', 'Třetí']]],
        ]);

        // Prázdný bod vypadne, ale číslování nesmí přeskočit z 01 na 03.
        $this->get('/zkusebni')
            ->assertOk()
            ->assertSeeInOrder(['01', 'První', '02', 'Třetí'], false);
    }

    public function test_prazdny_blok_se_nevykresli(): void
    {
        $this->makePage([
            ['type' => 'text', 'data' => ['body' => '']],
            ['type' => 'metrics', 'data' => ['title' => '', 'items' => []]],
            ['type' => 'quote', 'data' => ['text' => '', 'author' => 'Jan Novák']],
        ]);

        $this->get('/zkusebni')
            ->assertOk()
            ->assertDontSee('Jan Novák', false)
            ->assertDontSee('<section', false);
    }

    public function test_obrazek_v_bloku_dostane_verejnou_url(): void
    {
        $page = $this->makePage([
            ['type' => 'image', 'data' => ['image' => 'pages/foto.jpg', 'image_alt' => 'Popisek']],
        ]);

        $this->assertSame(
            '/storage/pages/foto.jpg',
            parse_url($page->contentBlocks()->first()['data']['image_url'], PHP_URL_PATH),
        );

        $this->get('/zkusebni')
            ->assertOk()
            ->assertSee('/storage/pages/foto.jpg', false)
            ->assertSee('alt="Popisek"', false);
    }

    /** @param  array<int, array{type: string, data: array<string, mixed>}>  $blocks */
    private function makePage(array $blocks): Page
    {
        return Page::create([
            'title' => 'Zkušební',
            'slug' => 'zkusebni',
            'published' => true,
            'blocks' => $blocks,
        ]);
    }
}
