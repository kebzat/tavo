<?php

namespace Tests\Feature;

use App\Enums\AutomationType;
use App\Enums\UserRole;
use App\Models\Automation;
use App\Models\Client;
use App\Models\User;
use App\Support\Automations\GiftBoxes;
use App\Support\Automations\UpgatesOrders;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Dárkové balíčky v přehledu automatizace: obsah z poznámky krabice v exportu Upgates. Viz docs/AUTOMATIZACE.md. */
class GiftBoxesTest extends TestCase
{
    use RefreshDatabase;

    /** Poznámka krabice tak, jak ji píše skládačka (objednávka 04405). */
    private const NOTE = 'Dárkový balíček: BELI MAL – Sušený květ 40 g, MASALA ČAJ - SMĚS 50 g, 2× SOURSOP - ZELENÝ ČAJ 20 G'
        .' – OBRÁZEK, JAK KRABICI POSKLÁDAT: https://svetcejlonu.tomaskebza.cz/b/sVqGtBlQ [balicek][38_110][55_198][88_218][88_218]';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-28 15:00:00', 'Europe/Prague'));
    }

    public function test_obsah_krabice_z_poznamky(): void
    {
        $box = GiftBoxes::fromNote('Dárková krabice velká (30 × 30 cm)', self::NOTE);

        $this->assertSame('Dárková krabice velká (30 × 30 cm)', $box['title']);
        $this->assertSame([
            ['qty' => 1, 'name' => 'BELI MAL – Sušený květ 40 g'],
            ['qty' => 1, 'name' => 'MASALA ČAJ - SMĚS 50 g'],
            ['qty' => 2, 'name' => 'SOURSOP - ZELENÝ ČAJ 20 G'],
        ], $box['items']);
        $this->assertSame(4, $box['count']);
        $this->assertSame('https://svetcejlonu.tomaskebza.cz/b/sVqGtBlQ', $box['preview']);

        // Starší popisek odkazu a balíček bez odkazu (server neodpověděl).
        $old = GiftBoxes::fromNote('Krabice', 'Dárkový balíček: ČERNÝ ČAJ 20 g – náhled krabice: https://svetcejlonu.tomaskebza.cz/b/XifeJCZp [balicek][37_106]');
        $this->assertSame([['qty' => 1, 'name' => 'ČERNÝ ČAJ 20 g']], $old['items']);
        $this->assertSame('https://svetcejlonu.tomaskebza.cz/b/XifeJCZp', $old['preview']);

        $bare = GiftBoxes::fromNote('Krabice', 'Dárkový balíček: KARDAMON 15 g, Skleněný louhovač [balicek][43_221][54_]');
        $this->assertSame(['KARDAMON 15 g', 'Skleněný louhovač'], array_column($bare['items'], 'name'));
        $this->assertSame(2, $bare['count']);
        $this->assertNull($bare['preview']);

        $this->assertNull(GiftBoxes::fromNote('Čaj', 'Prosím zabalit jako dárek'));
    }

    public function test_export_prida_balicky_bez_osobnich_udaju_a_prehled_je_seradeny(): void
    {
        $order = fn (string $number, string $status, string $created, ?string $note): string => '<ORDER><HEADER><CODE>'.$number.'</CODE><STATUS>'.$status.'</STATUS>'
            .'<CREATED_AT>'.$created.'</CREATED_AT><CUSTOMER_NOTE>Pro babičku Janu</CUSTOMER_NOTE></HEADER>'
            .'<CUSTOMER><FIRSTNAME>Jana</FIRSTNAME><EMAIL>jana@example.cz</EMAIL></CUSTOMER><ITEMS>'
            .'<ITEM><TITLE>Dárková krabice velká (30 × 30 cm)</TITLE><CODE>BAL-KRAB-VELKA</CODE><NOTE>'.htmlspecialchars((string) $note).'</NOTE><QUANTITY>1</QUANTITY></ITEM>'
            .'<ITEM><TITLE>BELI MAL</TITLE><NOTE></NOTE><QUANTITY>1</QUANTITY></ITEM>'
            .'</ITEMS></ORDER>';

        $orders = UpgatesOrders::parse('<?xml version="1.0" encoding="utf-8"?><ORDERS>'
            .$order('05002', 'Přijatá', '2026-10-28 09:00:00', self::NOTE)
            .$order('05001', 'Připraveno na odeslání', '2026-10-27 09:00:00', self::NOTE)
            .$order('05003', 'Odeslána', '2026-10-26 09:00:00', self::NOTE)
            .$order('04001', 'Vyřešeno', '2026-09-01 09:00:00', self::NOTE)
            .$order('05004', 'Stornována', '2026-10-28 10:00:00', self::NOTE)
            .$order('05005', 'Přijatá', '2026-10-28 11:00:00', null)
            .'</ORDERS>');

        $this->assertArrayNotHasKey('boxes', $orders['05005']);
        $this->assertStringNotContainsString('Jana', json_encode($orders, JSON_UNESCAPED_UNICODE));

        $automation = Automation::query()->create([
            'client_id' => Client::query()->firstOrCreate(['slug' => 'svet-cejlonu'], ['name' => 'Svět Cejlonu'])->id,
            'type' => AutomationType::UpgatesParcelPickup,
            'enabled' => true,
        ]);

        $overview = GiftBoxes::overview($automation, $orders);

        // K zabalení od nejstarší, pak odeslané; storno a staré vyřízené ne.
        $this->assertSame(['05001', '05002', '05003'], array_column($overview, 'order'));
        $this->assertSame([true, true, false], array_column($overview, 'to_pack'));
    }

    public function test_klient_vidi_balicky_k_zabaleni_s_obrazkem(): void
    {
        $client = Client::query()->firstOrCreate(['slug' => 'svet-cejlonu'], ['name' => 'Svět Cejlonu']);
        $box = GiftBoxes::fromNote('Dárková krabice velká (30 × 30 cm)', self::NOTE);
        Automation::query()->create([
            'client_id' => $client->id,
            'type' => AutomationType::UpgatesParcelPickup,
            'enabled' => true,
            'last_run_at' => now(),
            'snapshot' => ['parcels' => [], 'boxes' => [
                ['order' => '05001', 'status' => 'Přijatá', 'created_at' => '2026-10-28T09:00:00+01:00', 'to_pack' => true, 'boxes' => [$box]],
                ['order' => '05003', 'status' => 'Odeslána', 'created_at' => '2026-10-26T09:00:00+01:00', 'to_pack' => false, 'boxes' => [$box]],
            ]],
        ]);
        $this->actingAs(User::factory()->create(['role' => UserRole::Client, 'client_id' => $client->id]));

        $this->get('/muj-ucet/automatizace')
            ->assertOk()
            ->assertSee('Dárkové balíčky k zabalení (1)')
            ->assertSee('05001')
            ->assertSee('https://svetcejlonu.tomaskebza.cz/b/sVqGtBlQ.jpg', false)
            ->assertSee('SOURSOP - ZELENÝ ČAJ 20 G')
            ->assertSee('2×')
            ->assertSee('Odeslané balíčky za posledních 14 dní (1)');
    }

    public function test_bez_balicku_se_sekce_neukaze(): void
    {
        $client = Client::query()->create(['name' => 'Obchod', 'slug' => 'obchod']);
        Automation::query()->create(['client_id' => $client->id, 'type' => AutomationType::UpgatesParcelPickup, 'enabled' => true, 'snapshot' => ['parcels' => []]]);
        $this->actingAs(User::factory()->create(['role' => UserRole::Client, 'client_id' => $client->id]));

        $this->get('/muj-ucet/automatizace')->assertOk()->assertDontSee('Dárkové balíčky');
    }
}
