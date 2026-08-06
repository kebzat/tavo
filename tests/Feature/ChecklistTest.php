<?php

namespace Tests\Feature;

use App\Enums\ChecklistItemStatus;
use App\Enums\ChecklistPriority;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChecklistTest extends TestCase
{
    use RefreshDatabase;

    private function sablona(): Checklist
    {
        $template = Checklist::create([
            'is_template' => true,
            'name' => 'Vzorová šablona',
            'intro' => 'Úvodní text',
        ]);

        $category = $template->categories()->create([
            'title' => 'Měření a data',
            'slug' => 'mereni-a-data',
            'description' => 'Popisek na kartě',
            'order_column' => 1,
        ]);

        $section = $category->sections()->create(['title' => 'Nástroje', 'order_column' => 1]);

        $section->items()->createMany([
            [
                'title' => 'Nasadit GTM',
                'description' => 'Jediné místo pro správu skriptů.',
                'internal_note' => 'Tuhle poznámku klient vidět nesmí.',
                'priority' => ChecklistPriority::Must,
                'status' => ChecklistItemStatus::Done,
                'order_column' => 1,
            ],
            [
                'title' => 'Nasadit GA4',
                'priority' => ChecklistPriority::Should,
                'status' => ChecklistItemStatus::Skipped,
                'order_column' => 2,
            ],
            [
                'title' => 'Nasadit Meta Pixel',
                'priority' => ChecklistPriority::Nice,
                'status' => ChecklistItemStatus::Todo,
                'order_column' => 3,
            ],
        ]);

        return $template->fresh();
    }

    private function klient(): Client
    {
        $poradi = Client::count() + 1;

        return Client::create(['name' => 'Zkušební klient '.$poradi, 'slug' => 'zkusebni-klient-'.$poradi]);
    }

    private function sdilenyChecklist(): Checklist
    {
        $copy = $this->sablona()->duplicateFor($this->klient(), 'Checklist klienta');
        $copy->update(['is_public' => true]);

        return $copy->fresh();
    }

    /*
    |--------------------------------------------------------------------------
    | Struktura a klonování
    |--------------------------------------------------------------------------
    */

    public function test_klonovani_ze_sablony_zkopiruje_kategorie_sekce_i_polozky(): void
    {
        $client = $this->klient();

        $copy = $this->sablona()->duplicateFor($client, 'Checklist klienta');

        $this->assertSame('Checklist klienta', $copy->name);
        $this->assertSame($client->getKey(), $copy->client_id);
        $this->assertFalse($copy->is_template);
        $this->assertSame(1, $copy->categories()->count());
        $this->assertSame('mereni-a-data', $copy->categories()->first()->slug);
        $this->assertSame(1, $copy->sections()->count());
        $this->assertSame(3, $copy->items()->count());
    }

    public function test_klon_resetuje_stavy_a_zahodi_interni_poznamky(): void
    {
        $copy = $this->sablona()->duplicateFor($this->klient(), 'Checklist klienta');

        $this->assertSame(3, $copy->items()->where('status', ChecklistItemStatus::Todo->value)->count());
        $this->assertSame(0, $copy->items()->whereNotNull('internal_note')->count());
    }

    /** Bez vazby na checklist by souhrnná tabulka v administraci zůstala prázdná. */
    public function test_polozka_si_doplni_vazbu_na_checklist_sama(): void
    {
        $template = $this->sablona();
        $section = $template->categories()->first()->sections()->first();

        $item = $section->items()->create(['title' => 'Dodatečná položka']);

        $this->assertSame($template->getKey(), $item->fresh()->checklist_id);
    }

    public function test_sablona_nemuze_byt_verejna(): void
    {
        $template = $this->sablona();
        $template->update(['is_public' => true]);

        $this->assertFalse($template->fresh()->is_public);
        $this->assertNull($template->fresh()->publicUrl());
    }

    /*
    |--------------------------------------------------------------------------
    | Sdílená stránka
    |--------------------------------------------------------------------------
    */

    public function test_rozcestnik_ukaze_karty_kategorii(): void
    {
        $checklist = $this->sdilenyChecklist();

        $this->get($checklist->publicUrl())
            ->assertOk()
            ->assertSee('Checklist klienta')
            ->assertSee('Měření a data')
            ->assertSee('Popisek na kartě')
            // Položky patří až do detailu kategorie.
            ->assertDontSee('Nasadit GTM');
    }

    public function test_detail_kategorie_ukaze_sekce_i_polozky(): void
    {
        $checklist = $this->sdilenyChecklist();

        $this->get(route('checklist.category', [$checklist->public_token, 'mereni-a-data']))
            ->assertOk()
            ->assertSee('Nástroje')
            ->assertSee('Nasadit GTM')
            ->assertSee('Jediné místo pro správu skriptů.');
    }

    public function test_neznama_kategorie_vraci_404(): void
    {
        $checklist = $this->sdilenyChecklist();

        $this->get(route('checklist.category', [$checklist->public_token, 'takova-neni']))
            ->assertNotFound();
    }

    public function test_verejny_odkaz_neukaze_interni_poznamku(): void
    {
        $checklist = $this->sdilenyChecklist();

        // Poznámku doplníme až po naklonování, ať test nestojí jen na tom,
        // že ji duplicateFor() zahodí.
        $checklist->items()->first()->update(['internal_note' => 'Interní: klient platí pozdě.']);

        $this->get(route('checklist.category', [$checklist->public_token, 'mereni-a-data']))
            ->assertOk()
            ->assertDontSee('Interní: klient platí pozdě.');
    }

    public function test_verejny_checklist_se_nesmi_indexovat(): void
    {
        $this->get($this->sdilenyChecklist()->publicUrl())
            ->assertOk()
            ->assertSee('noindex, nofollow', false);
    }

    public function test_nezverejneny_checklist_vraci_404(): void
    {
        $copy = $this->sablona()->duplicateFor($this->klient(), 'Checklist klienta');

        $this->get(route('checklist.show', $copy->public_token))->assertNotFound();
        $this->get(route('checklist.category', [$copy->public_token, 'mereni-a-data']))->assertNotFound();
    }

    public function test_neplatny_token_vraci_404(): void
    {
        $this->get(route('checklist.show', 'takovy-token-neexistuje'))->assertNotFound();
    }

    public function test_sablona_neni_dostupna_pres_svuj_token(): void
    {
        $this->get(route('checklist.show', $this->sablona()->public_token))->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Odškrtávání
    |--------------------------------------------------------------------------
    */

    public function test_polozka_jde_odskrtnout_ze_sdilene_stranky(): void
    {
        $checklist = $this->sdilenyChecklist();
        $item = $checklist->items()->orderBy('id')->first();

        $this->post(route('checklist.toggle', [$checklist->public_token, $item]))
            ->assertRedirect();

        $this->assertSame(ChecklistItemStatus::Done, $item->fresh()->status);
    }

    public function test_druhy_klik_odskrtnuti_vrati(): void
    {
        $checklist = $this->sdilenyChecklist();
        $item = $checklist->items()->orderBy('id')->first();

        $this->post(route('checklist.toggle', [$checklist->public_token, $item]));
        $this->post(route('checklist.toggle', [$checklist->public_token, $item]));

        $this->assertSame(ChecklistItemStatus::Todo, $item->fresh()->status);
    }

    public function test_odskrtnuti_vrati_prepocitany_progres(): void
    {
        $checklist = $this->sdilenyChecklist();
        $item = $checklist->items()->orderBy('id')->first();

        $this->postJson(route('checklist.toggle', [$checklist->public_token, $item]))
            ->assertOk()
            ->assertJsonPath('done', true)
            ->assertJsonPath('celkem.done', 1)
            ->assertJsonPath('celkem.total', 3)
            ->assertJsonPath('kategorie.done', 1);
    }

    /** Jinak by cizí token dovolil přepnout položku v cizím checklistu. */
    public function test_cizim_tokenem_nejde_prepnout_cizi_polozka(): void
    {
        $muj = $this->sdilenyChecklist();
        $cizi = $this->sdilenyChecklist();

        $this->post(route('checklist.toggle', [$muj->public_token, $cizi->items()->first()]))
            ->assertNotFound();
    }

    public function test_nezverejnenym_checklistem_nejde_odskrtavat(): void
    {
        $copy = $this->sablona()->duplicateFor($this->klient(), 'Checklist klienta');

        $this->post(route('checklist.toggle', [$copy->public_token, $copy->items()->first()]))
            ->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Progres
    |--------------------------------------------------------------------------
    */

    /**
     * Přeskočené položky se počítají jako vyřízené. Nic už kvůli nim
     * nebrání spuštění, takže by neměly držet progres dole.
     */
    public function test_progres_pocita_hotove_i_preskocene_polozky(): void
    {
        $progress = $this->sablona()->progress();

        $this->assertSame(3, $progress['total']);
        $this->assertSame(2, $progress['done']);
        $this->assertSame(67, $progress['percent']);
    }

    public function test_prazdny_checklist_ma_nulovy_progres(): void
    {
        $checklist = Checklist::create(['name' => 'Prázdný']);

        $this->assertSame(['total' => 0, 'done' => 0, 'percent' => 0], $checklist->progress());
    }

    public function test_smazani_checklistu_odstrani_celou_strukturu(): void
    {
        $template = $this->sablona();
        $categoryId = $template->categories()->value('id');
        $itemIds = $template->items()->pluck('id');

        $template->delete();

        $this->assertDatabaseMissing('checklist_categories', ['id' => $categoryId]);
        $this->assertSame(0, ChecklistItem::whereIn('id', $itemIds)->count());
    }

    public function test_univerzalni_sablona_prisla_migraci(): void
    {
        $template = Checklist::templates()->first();

        $this->assertNotNull($template, 'Datová migrace šablonu nezaložila.');
        $this->assertSame(5, $template->categories()->count());
        $this->assertGreaterThan(100, $template->items()->count());
    }
}
