<?php

namespace Tests\Feature;

use App\Models\WebText;
use App\Support\WebTexts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Statické texty: v kódu zůstává výchozí znění, databáze drží jen to,
 * co si správce přepsal v administraci.
 */
class WebTextsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        WebTexts::forget();
    }

    public function test_neznamy_klic_vrati_vychozi_zneni_a_sam_se_zalozi(): void
    {
        $this->assertSame('Výchozí znění.', text('zkouska.pozdrav', 'Výchozí znění.'));

        $this->assertDatabaseHas('web_texts', [
            'key' => 'zkouska.pozdrav',
            'value' => 'Výchozí znění.',
        ]);
    }

    public function test_ulozeny_text_prebije_zneni_ze_sablony(): void
    {
        text('zkouska.pozdrav', 'Výchozí znění.');

        $record = WebText::where('key', 'zkouska.pozdrav')->sole();
        $record->value = 'Přepsáno v administraci.';
        $record->save();

        $this->assertSame('Přepsáno v administraci.', text('zkouska.pozdrav', 'Výchozí znění.'));
    }

    public function test_smazani_vrati_zneni_ze_sablony(): void
    {
        text('zkouska.pozdrav', 'Výchozí znění.');

        $record = WebText::where('key', 'zkouska.pozdrav')->sole();
        $record->value = 'Přepsáno v administraci.';
        $record->save();
        $record->delete();

        $this->assertSame('Výchozí znění.', text('zkouska.pozdrav', 'Výchozí znění.'));
        $this->assertDatabaseHas('web_texts', ['key' => 'zkouska.pozdrav', 'value' => 'Výchozí znění.']);
    }

    public function test_skupinu_odvodi_z_klice_a_poznamku_ulozi(): void
    {
        text('newsletter.nadpis', 'Odebírejte novinky', null, 'Nadpis nad přihlášením k odběru');

        $this->assertDatabaseHas('web_texts', [
            'key' => 'newsletter.nadpis',
            'group' => 'Newsletter',
            'note' => 'Nadpis nad přihlášením k odběru',
        ]);
    }

    public function test_vypis_referenci_bere_texty_z_databaze(): void
    {
        $this->get('/reference')->assertOk()->assertSee('Na čem jsme', false);

        $record = WebText::where('key', 'reference.nadpis')->sole();
        $record->value = 'Naše práce';
        $record->save();

        $this->get('/reference')
            ->assertOk()
            ->assertSee('Naše práce', false)
            ->assertDontSee('Na čem jsme', false);
    }
}
