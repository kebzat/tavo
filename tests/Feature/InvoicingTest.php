<?php

namespace Tests\Feature;

use App\Enums\Crm\DealPackage;
use App\Enums\Crm\DealStage;
use App\Enums\UserRole;
use App\Enums\WorkArea;
use App\Filament\Tools\Pages\Invoicing;
use App\Models\Client;
use App\Models\ClientInvoice;
use App\Models\Crm\Deal;
use App\Models\User;
use App\Support\Ads\Billing;
use App\Support\Ads\Format;
use App\Support\ClientDashboard;
use App\Support\RetainerOutlook;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class InvoicingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-20 10:00');
        Filament::setCurrentPanel('tools');
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    }

    private function klient(): Client
    {
        $client = Client::create(['name' => 'Paušál Zkouška', 'slug' => 'pausal-zkouska']);
        $client->retainers()->create(['area' => WorkArea::Web, 'label' => 'Vývoj webu', 'monthly_fee' => 8000]);

        return $client;
    }

    public function test_samotny_pausal_bez_hodin_jde_oznacit_jako_vyfakturovany(): void
    {
        $client = $this->klient();

        $this->assertFalse(Billing::for($client, now())->isInvoiced());

        Livewire::test(Invoicing::class)->call('markInvoiced', $client->id);

        $billing = Billing::for($client, now());
        $this->assertTrue($billing->isInvoiced());
        $this->assertSame(8000, $billing->invoice->amount_czk);
        $this->assertFalse($billing->changedSinceInvoice());

        // Jiný měsíc čeká dál.
        $this->assertFalse(Billing::for($client, now()->subMonth())->isInvoiced());
    }

    public function test_hodiny_dopsane_po_fakture_se_ukazou(): void
    {
        $client = $this->klient();
        Billing::for($client, now())->markInvoiced();

        $client->timeEntries()->create(['worked_on' => now(), 'minutes' => 60, 'description' => 'Oprava košíku', 'billable' => true]);

        $this->assertTrue(Billing::for($client, now())->changedSinceInvoice());

        Livewire::test(Invoicing::class)->call('unmarkInvoiced', $client->id);

        $this->assertSame(0, ClientInvoice::count());
    }

    public function test_vyhrana_jednorazova_zakazka_ceka_na_fakturu_i_v_dalsich_mesicich(): void
    {
        $won = Deal::factory()->create(['title' => 'Migrace Zkouška', 'package' => DealPackage::MigrationShoptet, 'value_czk' => 40000]);
        $won->update(['stage' => DealStage::Won]);
        $retainer = Deal::factory()->create(['title' => 'Správa Zkouška', 'package' => DealPackage::Retainer, 'value_czk' => 9000]);
        $retainer->update(['stage' => DealStage::Won]);

        Carbon::setTestNow('2026-12-05 10:00');

        Livewire::test(Invoicing::class)
            ->assertSee('Migrace Zkouška')
            ->assertDontSee('Správa Zkouška')
            ->call('markDealInvoiced', $won->id);

        $this->assertNotNull($won->fresh()->invoiced_at);
        $this->assertSame(0, Deal::query()->billable()->whereNull('invoiced_at')->count());
    }

    public function test_odznak_v_menu_pocita_nevyfakturovany_minuly_mesic(): void
    {
        $client = $this->klient();
        $before = (int) Invoicing::getNavigationBadge();

        Billing::for($client, now()->subMonth())->markInvoiced();

        $this->assertSame($before - 1, (int) Invoicing::getNavigationBadge());
    }

    public function test_stranka_se_nacte(): void
    {
        $this->klient();

        $this->get('/nastroje/fakturace')
            ->assertOk()
            ->assertSee('Paušál Zkouška')
            ->assertSee('Zbývá vyfakturovat')
            ->assertSee('Jednorázové zakázky');
    }

    /** Teď 30 000 na web do prosince, od ledna předběžně 10 000. */
    private function klientSeZmenou(): Client
    {
        $client = Client::create(['name' => 'Výhled Zkouška', 'slug' => 'vyhled-zkouska', 'started_on' => '2026-10-01', 'dashboard_enabled' => true]);
        $client->retainers()->createMany([
            ['area' => WorkArea::Web, 'label' => 'Vývoj webu', 'monthly_fee' => 30000, 'starts_on' => '2026-10-01', 'ends_on' => '2026-12-31'],
            ['area' => WorkArea::Web, 'label' => 'Vývoj webu', 'monthly_fee' => 10000, 'starts_on' => '2027-01-01', 'is_tentative' => true],
        ]);

        return $client;
    }

    public function test_predbezny_pausal_se_nefakturuje_ani_neukaze_klientovi(): void
    {
        $client = $this->klientSeZmenou();

        $this->assertEquals(30000, Billing::for($client, now())->total());

        $january = Billing::for($client, Carbon::parse('2027-01-01'));
        $this->assertEquals(0, $january->total());
        $this->assertEquals(10000, $january->tentativeFee);

        Carbon::setTestNow('2027-01-15 10:00');
        $this->assertNull(ClientDashboard::for($client->fresh())->totalFee());
    }

    public function test_vyhled_ukaze_pokles_po_mesicich(): void
    {
        $this->klientSeZmenou();
        Client::where('slug', '!=', 'vyhled-zkouska')->update(['is_archived' => true]);

        $outlook = new RetainerOutlook;
        $row = $outlook->rows()->firstWhere('name', 'Výhled Zkouška');

        $this->assertSame('Domluveno do prosince 2026', $row['note']);
        $this->assertFalse($row['cells'][2]['tentative']);
        $this->assertTrue($row['cells'][3]['tentative']);

        $summary = $outlook->summary();
        $this->assertSame(Format::money(30000), $summary['now']);
        $this->assertSame(Format::money(30000), $summary['gap']);

        $this->get('/nastroje/vyhled')->assertOk()->assertSee('Výhled Zkouška')->assertSee('Domluveno do prosince 2026');
    }
}
