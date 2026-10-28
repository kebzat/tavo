<?php

namespace Tests\Feature;

use App\Enums\AutomationType;
use App\Enums\UserRole;
use App\Enums\VaultType;
use App\Mail\AutomationProblem;
use App\Mail\AutomationSummary;
use App\Models\Automation;
use App\Models\AutomationEvent;
use App\Models\Client;
use App\Models\User;
use App\Models\VaultEntry;
use App\Support\Automations\AutomationException;
use App\Support\Automations\PacketaTracking;
use App\Support\Automations\ParcelPickup;
use App\Support\Automations\UpgatesImportUpload;
use App\Support\Automations\UpgatesOrders;
use App\Support\Vault\LoginBroker;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Připomínky vyzvednutí zásilek: engine, CSV pro Upgates, upozornění. Viz docs/AUTOMATIZACE.md. */
class AutomationsTest extends TestCase
{
    use RefreshDatabase;

    private const FULL = 'https://svetcejlonu.test/export/vse.xml';

    private const DAY = 'https://svetcejlonu.test/export/24h.xml';

    private Client $client;

    private Automation $automation;

    /** @var array<string, array{0: string, 1: ?string}> číslo => [stav, updated_at] v kompletním exportu */
    private array $full = [];

    /** @var array<string, array{0: string, 1: ?string}> změny za 24 h */
    private array $day = [];

    /** @var list<array<string, mixed>> aktivní zásilky v Zaslatu */
    private array $shipments = [];

    /** @var array<string, array<string, mixed>> sledování zásilek v Zaslatu (POST /shipments/tracking), číslo zásilky => data */
    private array $tracking = [];

    /** @var array<string, int> URL exportu => HTTP stav, když má selhat */
    private array $broken = [];

    /** @var list<string> soubory, které by web nahrál do importu v Upgates (UTF-8) */
    private array $uploads = [];

    /** Odpověď Upgates na nahraný import. */
    private bool $uploadAccepted = true;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->travelTo(CarbonImmutable::parse('2026-10-27 10:40:00', 'Europe/Prague'));

        $this->client = Client::query()->firstOrCreate(['slug' => 'svet-cejlonu'], ['name' => 'Svět Cejlonu']);
        $entry = VaultEntry::query()->create([
            'client_id' => $this->client->id,
            'type' => VaultType::ApiKey,
            'name' => 'Zaslat API',
            'url' => 'zaslat.cz',
            'secret' => 'tajny-klic-zaslat',
            'agent_access' => 'read',
        ]);

        $this->automation = Automation::query()->create([
            'client_id' => $this->client->id,
            'type' => AutomationType::UpgatesParcelPickup,
            'enabled' => true,
            'settings' => ['vault_entry_id' => $entry->id, 'reminders_since' => '2026-10-01'],
            'order_export_url' => self::FULL."\n".self::DAY,
        ]);

        Http::fake(fn (Request $request) => $this->respond($request));

        // Nahrávání do Upgates jen zaznamenat, skutečné přihlášení a nahrání má vlastní test.
        $uploads = &$this->uploads;
        $this->app->instance(UpgatesImportUpload::class, new class(app(LoginBroker::class), $uploads) extends UpgatesImportUpload
        {
            public function __construct(LoginBroker $broker, private array &$uploads)
            {
                parent::__construct($broker);
            }

            public function upload(Automation $automation, string $csv): void
            {
                $this->uploads[] = iconv('Windows-1250', 'UTF-8', $csv);
            }
        });
    }

    private function respond(Request $request)
    {
        $url = strtok($request->url(), '?');

        if (isset($this->broken[$url])) {
            return Http::response('chyba', $this->broken[$url]);
        }

        if ($url === self::FULL || $url === self::DAY) {
            return Http::response($this->xml($url === self::FULL ? $this->full : $this->day));
        }

        if (str_starts_with($url, 'https://www.zaslat.cz/api/v1/shipments/list')) {
            $offset = (int) ($request->data()['offset'] ?? 0);
            $page = array_slice($this->shipments, $offset, 50);

            return Http::response([
                'status' => 200,
                'message' => 'OK',
                'meta' => ['offset' => $offset, 'limit' => 50, 'total' => count($this->shipments)],
                'data' => collect($page)->keyBy(fn (array $shipment): string => 'ZA'.$shipment['reference'])->all(),
            ]);
        }

        if ($url === 'https://www.zaslat.cz/api/v1/shipments/tracking') {
            return Http::response(['status' => 200, 'message' => 'OK', 'data' => array_intersect_key($this->tracking, array_flip((array) ($request->data()['shipments'] ?? [])))]);
        }

        if (str_starts_with($url, 'https://tracking.packeta.com/')) {
            return Http::response(self::packetaHtml('2026-10-29'));
        }

        if ($url === 'https://svetcejlonu-1.admin.test/login/') {
            return $request->method() === 'GET'
                ? Http::response('<form method="post" action="/login/?do=loginForm-submit"><input name="email"><input type="password" name="password"><input type="hidden" name="_token_" value="t1"></form>')
                : Http::response('<h1>Přehled</h1>', 200, ['Set-Cookie' => 'PHPSESSID=relace; path=/; domain=svetcejlonu-1.admin.test']);
        }

        if ($url === 'https://svetcejlonu-1.admin.test/setup/export-import/default/orders/') {
            return Http::response(['success' => $this->uploadAccepted, 'state' => []]);
        }

        return Http::response('', 404);
    }

    /** @param array<string, array{0: string, 1: ?string}> $orders */
    private function xml(array $orders): string
    {
        $xml = '<?xml version="1.0" encoding="utf-8"?><ORDERS version="1.0">';

        foreach ($orders as $number => [$status, $updated]) {
            // Osobní údaje v exportu jsou, engine je nesmí nikam uložit.
            $xml .= '<ORDER><ORDER_NUMBER>'.$number.'</ORDER_NUMBER><STATUS>'.$status.'</STATUS>'
                .($updated ? '<UPDATED_AT>'.$updated.'</UPDATED_AT>' : '')
                .'<CUSTOMER><FIRSTNAME>Jana</FIRSTNAME><SURNAME>Nováková</SURNAME><EMAIL>jana@example.cz</EMAIL><PHONE>+420777123456</PHONE></CUSTOMER></ORDER>';
        }

        return $xml.'</ORDERS>';
    }

    public static function packetaHtml(string $date): string
    {
        $payload = json_encode([
            ['ShallowReactive', 1],
            ['data' => 2],
            ['packet' => 3],
            ['barcode' => 7, 'packetStatus' => 8, 'packetStatusId' => 9, 'storedUntil' => 10, 'canProlong' => 11, 'eshop' => 12],
            'x', 'y', 'z',
            'Z 123 4567 890',
            'Zásilka je připravena k vyzvednutí',
            '2',
            $date,
            true,
            'Svět Cejlonu',
        ], JSON_UNESCAPED_UNICODE);

        return '<!doctype html><html><body><div id="__nuxt"></div><script type="application/json" data-nuxt-data="nuxt-app" id="__NUXT_DATA__">'.$payload.'</script></body></html>';
    }

    /** Zásilka, která od `$arrived` leží na výdejním místě. */
    private function shipment(string $order, ?string $arrived, string $carrier = 'ppl', string $status = 'PARCELSHOP', bool $returned = false): array
    {
        $events = array_values(array_filter([
            ['date' => '2026-10-26', 'time' => '09:55:00', 'status' => $status, 'name' => 'Uloženo', 'location' => 'Výdejní místo Praha 4'],
            $arrived ? ['date' => $arrived, 'time' => '08:00:00', 'status' => 'PARCELSHOP', 'name' => 'Připraveno', 'location' => 'Výdejní místo Praha 4'] : null,
            ['date' => '2026-10-18', 'time' => '17:00:00', 'status' => 'INTRANSIT', 'name' => 'Na cestě', 'location' => 'Depo'],
        ]));

        return [
            'carrier' => $carrier,
            'status' => $status,
            'reference' => $order,
            'packages' => [['carrier_tracking' => $carrier === 'zasilkovna' ? 'https://tracking.packeta.com/cs/?id=Z'.$order : 'https://www.ppl.cz/vyhledat-zasilku?shipmentId='.$order]],
            'tracking_information' => ['status' => $status, 'returned' => $returned, 'packages' => [$events]],
        ];
    }

    private function runEngine(): array
    {
        return app(ParcelPickup::class)->run($this->automation->fresh());
    }

    /** @return array<string, string> číslo => druh */
    private function events(): array
    {
        return AutomationEvent::query()->orderBy('order_number')->pluck('kind', 'order_number')->all();
    }

    public function test_pripominka_posledni_den_a_vracena_zasilka_se_zalozi_jen_jednou(): void
    {
        $this->full = [
            '1001' => ['Odeslána', null],               // PPL, do 29. 10. → za 2 dny → připomínka
            '1002' => ['Odeslána', null],               // PPL, do 27. 10. → dnes poslední den
            '1003' => ['Čeká na vyzvednutí ', null],    // vrácená (stav s mezerou na konci)
            '1004' => ['Odeslána', null],               // do 1. 11. → ještě nic
            '1005' => ['Vyřešeno', null],               // vyřízená ručně → nesahat
            '1006' => ['Odeslána', null],               // Zásilkovna, storedUntil 29. 10. ze sledování
        ];
        $this->shipments = [
            $this->shipment('1001', '2026-10-22'),
            $this->shipment('1002', '2026-10-20'),
            $this->shipment('1003', null, 'dpd', 'INTRANSIT', returned: true),
            $this->shipment('1004', '2026-10-25'),
            $this->shipment('1005', '2026-10-20'),
            $this->shipment('1006', null, 'zasilkovna'),
            $this->shipment('1007', '2026-10-20'),           // v exportu není → nesahat
            $this->shipment('', '2026-10-20'),               // bez čísla objednávky
        ];

        $report = $this->runEngine();

        $this->assertNull($report['error']);
        $this->assertSame([
            '1001' => AutomationEvent::KIND_REMINDER,
            '1002' => AutomationEvent::KIND_LAST_DAY,
            '1003' => AutomationEvent::KIND_RETURNED,
            '1006' => AutomationEvent::KIND_REMINDER,
        ], $this->events());

        $reminder = AutomationEvent::query()->where('order_number', '1001')->first();
        $this->assertSame('Čeká na vyzvednutí', $reminder->target_status);
        $this->assertSame(AutomationEvent::STATE_PENDING, $reminder->state);
        $this->assertSame('2026-10-29', $reminder->stored_until->toDateString());
        $this->assertSame('Poslední den k vyzvednutí', AutomationEvent::query()->where('order_number', '1002')->value('target_status'));
        $this->assertSame('Vráceno', AutomationEvent::query()->where('order_number', '1003')->value('target_status'));
        $this->assertSame('2026-10-29', AutomationEvent::query()->where('order_number', '1006')->first()->stored_until->toDateString());

        // Druhý běh nic nového nezaloží.
        $this->runEngine();
        $this->assertSame(4, AutomationEvent::query()->count());

        // Přehled zásilek bez osobních údajů a míst.
        $automation = $this->automation->fresh();
        $this->assertNotNull($automation->last_run_at);
        $this->assertNull($automation->last_error);
        $parcels = collect($automation->parcels())->keyBy('order');
        $this->assertEqualsCanonicalizing(['1001', '1002', '1004', '1005', '1006', '1007'], $parcels->keys()->map(fn ($key): string => (string) $key)->all());
        $this->assertSame(2, $parcels['1001']['days_left']);
        $this->assertSame(['reminder'], $parcels['1001']['sent']);
        $this->assertSame(0, $parcels['1002']['days_left']);
        $snapshot = json_encode($automation->snapshot, JSON_UNESCAPED_UNICODE);
        foreach (['Jana', 'Nováková', 'jana@example.cz', '777123456', 'Praha'] as $private) {
            $this->assertStringNotContainsString($private, $snapshot);
        }
    }

    public function test_pripominka_jen_z_odeslane_a_posledni_den_i_po_pripomince(): void
    {
        $this->full = ['2001' => ['Čeká na vyzvednutí', null], '2002' => ['Poslední den k vyzvednutí', null]];
        $this->shipments = [$this->shipment('2001', '2026-10-22'), $this->shipment('2002', '2026-10-20')];

        $this->runEngine();

        // 2001 už stav připomínky má (ručně), 2002 už má cílový stav posledního dne.
        $this->assertSame([], $this->events());

        $this->travel(2)->days();
        $this->runEngine();

        $this->assertSame(['2001' => AutomationEvent::KIND_LAST_DAY], $this->events());
    }

    public function test_vracejici_se_a_rozvazena_zasilka_nedostane_sms_ani_pripominku(): void
    {
        $this->full = ['5001' => ['Odeslána', null], '5002' => ['Odeslána', null], '5003' => ['Odeslána', null]];
        $returning = $this->shipment('5001', '2026-10-20');
        array_unshift($returning['tracking_information']['packages'][0],
            ['date' => '2026-10-27', 'time' => '10:38:00', 'status' => 'INTRANSIT', 'name' => 'Zásilku jsme převzali od odesílatele.'],
            ['date' => '2026-10-26', 'time' => '16:48:00', 'status' => 'PARCELSHOP', 'name' => 'Zásilka nebyla příjemcem vyzvednuta a bude vrácena odesílateli.'],
        );
        $backInDelivery = $this->shipment('5002', '2026-10-20', 'dpd');
        array_unshift($backInDelivery['tracking_information']['packages'][0],
            ['date' => '2026-10-27', 'time' => '07:52:00', 'status' => 'ONDELIVERY', 'name' => 'Zásilka je v rozvozu'],
        );
        $expired = $this->shipment('5003', '2026-10-19');      // úložní doba skončila 26. 10.
        $this->shipments = [$returning, $backInDelivery, $expired];

        $this->runEngine();

        $this->assertSame([], $this->events());
        $this->assertSame([], collect($this->automation->fresh()->parcels())->pluck('order')->map(fn ($order): string => (string) $order)->intersect(['5001', '5002'])->values()->all());
    }

    public function test_vracena_zasilka_ve_stavu_vyreseno_upozorni_jednou(): void
    {
        Mail::fake();
        User::factory()->create(['role' => UserRole::Admin]);
        $this->full = ['6001' => ['Odeslána', null], '6002' => ['Odeslána', null], '6003' => ['Odeslána', null], '6004' => ['Odeslána', null]];
        foreach (['6001', '6002', '6003'] as $order) {
            $shipment = $this->shipment($order, null, 'ppl', 'ONDELIVERY');
            array_unshift($shipment['tracking_information']['packages'][0],
                ['date' => '2026-10-26', 'time' => '13:55:00', 'status' => 'ONDELIVERY', 'name' => 'The shipment has not been collected by the recipient and will be returned to the sender.'],
            );
            $this->shipments[] = $shipment;
        }
        $this->shipments[] = $this->shipment('6004', null, 'ppl', 'ONDELIVERY');   // obyčejně v rozvozu

        $this->runEngine();

        $this->assertSame(['6001', '6002', '6003'], collect($this->automation->fresh()->snapshot['returning'])->pluck('order')->all());
        $returning = collect($this->automation->fresh()->returns())->firstWhere('order', '6001');
        $this->assertEquals(['carrier' => 'ppl', 'returned' => false, 'status' => 'Odeslána'], Arr::only($returning, ['carrier', 'returned', 'status']));
        Mail::assertNothingSent();

        // Zásilky zmizely z aktivních. 6001 se vrátila, Zaslat ji vzal jako doručenou a dal Vyřešeno,
        // 6002 nakonec převzal příjemce, 6003 má správně Vráceno, 6004 doručená normálně.
        $this->travel(1)->days();
        $this->shipments = [];
        $this->day = ['6001' => ['Vyřešeno', '2026-10-28 12:00:00'], '6002' => ['Vyřešeno', '2026-10-28 12:00:00'], '6003' => ['Vráceno', '2026-10-28 12:00:00'], '6004' => ['Vyřešeno', '2026-10-28 12:00:00']];
        $this->tracking = [
            'ZA6001' => ['status' => 'DELIVERED', 'returned' => false, 'packages' => [[
                ['date' => '2026-10-28', 'time' => '12:00:37', 'status' => 'DELIVERED', 'name' => 'We have returned the shipment to the sender.'],
                ['date' => '2026-10-26', 'time' => '13:55:00', 'status' => 'ONDELIVERY', 'name' => 'The shipment has not been collected by the recipient and will be returned to the sender.'],
            ]]],
            'ZA6002' => ['status' => 'DELIVERED', 'returned' => false, 'packages' => [[
                ['date' => '2026-10-28', 'time' => '10:00:00', 'status' => 'DELIVERED', 'name' => 'Zásilku jsme doručili příjemci.'],
                ['date' => '2026-10-26', 'time' => '13:55:00', 'status' => 'ONDELIVERY', 'name' => 'The shipment has not been collected by the recipient and will be returned to the sender.'],
            ]]],
        ];

        $report = $this->runEngine();

        $this->assertSame(['6001'], $report['resolved_returns']);
        Mail::assertSent(AutomationProblem::class, 1);
        // 6001 zůstane v přehledu vrácených (už prověřená), 6002 převzal příjemce a zmizí.
        $returns = collect($this->automation->fresh()->returns())->keyBy('order');
        $this->assertSame(['6001', '6003'], $returns->pluck('order')->sort()->values()->all());
        $this->assertTrue($returns['6001']['returned'] && $returns['6001']['checked']);
        $this->assertSame('Vráceno', $returns['6003']['status']);
        Http::assertSent(fn (Request $request): bool => str_ends_with(strtok($request->url(), '?'), '/shipments/tracking')
            && $request->data()['shipments'] === ['ZA6001', 'ZA6002']);

        $this->travel(7)->hours();
        $this->runEngine();
        Mail::assertSent(AutomationProblem::class, 1);

        // Po třech týdnech se přestane hlídat i zásilka, která skončila správně jako vrácená.
        $this->travel(21)->days();
        $this->runEngine();
        $this->assertSame([], $this->automation->fresh()->snapshot['returning']);
    }

    public function test_po_zapnuti_pripominka_jen_u_novych_zasilek_a_posledni_den_u_vsech(): void
    {
        $this->automation->update(['enabled' => false]);
        $this->automation->update(['enabled' => true]);
        $this->assertSame('2026-10-27', $this->automation->fresh()->setting('reminders_since'));

        $this->full = ['6001' => ['Odeslána', null], '6002' => ['Odeslána', null], '6003' => ['Odeslána', null]];
        $this->shipments = [
            $this->shipment('6001', '2026-10-22'),   // dorazila před zapnutím, do 29. 10. → připomínka ne
            $this->shipment('6002', '2026-10-20'),   // dorazila před zapnutím, dnes poslední den → SMS ano
            $this->shipment('6003', null),
        ];
        // 6003 dorazí až 28. 10., úložní doba do 4. 11.
        $this->shipments[2]['tracking_information']['packages'][0] = [
            ['date' => '2026-10-28', 'time' => '09:00:00', 'status' => 'PARCELSHOP', 'name' => 'Zásilku jsme doručili na výdejní místo.'],
            ['date' => '2026-10-27', 'time' => '07:00:00', 'status' => 'ONDELIVERY', 'name' => 'Zásilka se dnes doručuje.'],
        ];

        $this->runEngine();
        $this->assertSame(['6002' => AutomationEvent::KIND_LAST_DAY], $this->events());

        $this->travel(6)->days();                 // 2. 11.: 6003 má do 4. 11. → připomínka
        $this->runEngine();
        $this->assertSame(AutomationEvent::KIND_REMINDER, $this->events()['6003'] ?? null);
        $this->assertArrayNotHasKey('6001', $this->events());
    }

    public function test_cekajici_zmeny_se_nahraji_do_importu_v_upgates(): void
    {
        $this->full = ['7001' => ['Odeslána', null], '7002' => ['Odeslána', null]];
        $this->shipments = [$this->shipment('7001', '2026-10-22'), $this->shipment('7002', '2026-10-25')];

        $report = $this->runEngine();

        $this->assertNull($report['error']);
        $this->assertSame(1, $report['uploaded']);
        $this->assertSame(["[ORDER_NUMBER];[ORDER_STATUS]\r\n7001;Čeká na vyzvednutí\r\n"], $this->uploads);
        $this->assertNotNull(AutomationEvent::query()->where('order_number', '7001')->value('exported_at'));

        // Upgates stav změnil (export za 24 h) → potvrzeno, bez čekajících změn se nic nenahrává.
        $this->day['7001'] = ['Čeká na vyzvednutí', '2026-10-27T10:45:00+01:00'];
        $this->runEngine();
        $this->assertCount(1, $this->uploads);
    }

    public function test_nahrani_importu_se_prihlasi_z_trezoru_a_posle_soubor(): void
    {
        $entry = VaultEntry::query()->create([
            'client_id' => $this->client->id,
            'type' => VaultType::Upgates,
            'name' => 'Upgates administrace',
            'url' => 'https://svetcejlonu-1.admin.test',
            'username' => 'automatizace@taveo.cz',
            'secret' => 'tajne-heslo',
            'settings' => ['login_url' => 'https://svetcejlonu-1.admin.test/login/'],
            'agent_access' => 'none',
        ]);
        $this->automation->update(['settings' => [...$this->automation->settings, 'upgates_vault_entry_id' => $entry->id, 'upgates_import_id' => 12]]);
        $csv = iconv('UTF-8', 'Windows-1250', "[ORDER_NUMBER];[ORDER_STATUS]\r\n7001;Čeká na vyzvednutí\r\n");

        (new UpgatesImportUpload(app(LoginBroker::class)))->upload($this->automation->fresh(), $csv);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://svetcejlonu-1.admin.test/setup/export-import/default/orders/?do=runAgainWithNewFile'
            && $request->hasHeader('X-Requested-With', 'XMLHttpRequest')
            && str_contains($request->header('Cookie')[0] ?? '', 'PHPSESSID=relace')
            && collect($request->data())->contains(fn ($part): bool => ($part['name'] ?? null) === 'import_id' && (string) $part['contents'] === '12')
            && collect($request->data())->contains(fn ($part): bool => ($part['name'] ?? null) === 'file' && $part['contents'] === $csv));
        $this->assertDatabaseHas('vault_logs', ['vault_entry_id' => $entry->id, 'action' => 'upgates_import']);

        $this->uploadAccepted = false;
        try {
            (new UpgatesImportUpload(app(LoginBroker::class)))->upload($this->automation->fresh(), $csv);
            $this->fail('Odmítnutý import má skončit chybou.');
        } catch (AutomationException $e) {
            $this->assertStringContainsString('nepřijal', $e->getMessage());
        }

        // Bez přihlášení v nastavení automatizace se nic neposílá.
        $this->automation->update(['settings' => [...$this->automation->settings, 'upgates_vault_entry_id' => null]]);
        $this->expectException(AutomationException::class);
        (new UpgatesImportUpload(app(LoginBroker::class)))->upload($this->automation->fresh(), $csv);
    }

    public function test_v_noci_zmeny_pockaji_do_rana_a_chyba_se_pocita_od_nahrani(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-28 01:40:00', 'Europe/Prague'));
        $this->full = ['7101' => ['Odeslána', null]];
        $this->shipments = [$this->shipment('7101', '2026-10-23')];

        $report = $this->runEngine();

        $this->assertSame(['7101' => AutomationEvent::KIND_REMINDER], $this->events());
        $this->assertSame([], $this->uploads);
        $this->assertSame(1, $report['held']);

        // Ráno v 8:40 se nahraje. Že čekala přes noc, chyba není.
        $this->travelTo(CarbonImmutable::parse('2026-10-28 08:40:00', 'Europe/Prague'));
        $report = $this->runEngine();

        $this->assertSame([], $report['failed']);
        $this->assertSame(1, $report['uploaded']);
        $this->assertSame(AutomationEvent::STATE_PENDING, AutomationEvent::query()->value('state'));

        // Chyba až 3 h od nahrání, kdyby Upgates stav nezměnil.
        $this->travel(2)->hours();
        $this->assertSame([], $this->runEngine()['failed']);
        $this->travel(2)->hours();
        $this->assertSame(['7101'], $this->runEngine()['failed']);

        // Klient si okno může změnit, od >= do znamená bez omezení.
        $this->automation->update(['settings' => [...$this->automation->settings, 'send_from' => 0, 'send_until' => 0]]);
        $this->assertTrue($this->automation->fresh()->canSendNow(CarbonImmutable::parse('2026-10-28 03:00:00', 'Europe/Prague')));
        $this->assertFalse(Automation::query()->make()->canSendNow(CarbonImmutable::parse('2026-10-28 20:00:00', 'Europe/Prague')));
        $this->assertTrue(Automation::query()->make()->canSendNow(CarbonImmutable::parse('2026-10-28 19:59:00', 'Europe/Prague')));
    }

    public function test_klient_vidi_vracene_zasilky(): void
    {
        $this->automation->update(['last_run_at' => now(), 'snapshot' => ['parcels' => [], 'returning' => [
            ['tracking' => 'ZA1', 'order' => '04138', 'since' => '2026-10-25', 'carrier' => 'zasilkovna', 'returned' => true, 'status' => 'Vráceno'],
            ['tracking' => 'ZA2', 'order' => '04150', 'since' => '2026-10-27', 'carrier' => 'ppl', 'returned' => false, 'status' => 'Odeslána'],
            ['tracking' => 'ZA3', 'order' => '04001', 'since' => '2026-10-20'],   // zapsaná před 28. 10., bez dopravce
        ]]]);
        $this->actingAs(User::factory()->create(['role' => UserRole::Client, 'client_id' => $this->client->id]));

        $this->get('/muj-ucet/automatizace')
            ->assertOk()
            ->assertSee('Vrácené zásilky za posledních 21 dní (3)')
            ->assertSeeInOrder(['04150', 'Na cestě zpět', '04138', 'Zásilkovna', 'Vrátila se', 'Vráceno', '04001'])
            ->assertSee('8:00–20:00');
    }

    public function test_vypnute_druhy_zprav_se_neposilaji(): void
    {
        $this->automation->update(['settings' => [...$this->automation->settings, 'send_reminder' => false, 'send_returned' => false, 'remind_days_before' => 3]]);
        $this->full = ['3001' => ['Odeslána', null], '3002' => ['Odeslána', null], '3003' => ['Odeslána', null]];
        $this->shipments = [
            $this->shipment('3001', '2026-10-22'),
            $this->shipment('3002', null, 'dpd', 'INTRANSIT', returned: true),
            $this->shipment('3003', '2026-10-20'),
        ];

        $this->runEngine();

        $this->assertSame(['3003' => AutomationEvent::KIND_LAST_DAY], $this->events());
    }

    public function test_potvrzeni_podle_exportu_a_chyba_po_trech_hodinach(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => UserRole::Admin, 'email' => 'tom@taveo.cz']);
        $marek = User::factory()->create(['role' => UserRole::Client, 'client_id' => $this->client->id, 'email' => 'info@svetcejlonu.cz']);
        $other = User::factory()->create(['role' => UserRole::Client, 'client_id' => Client::create(['name' => 'Jiný', 'slug' => 'jiny'])->id]);
        User::factory()->create(['role' => UserRole::Editor, 'email' => 'redaktor@taveo.cz']);

        $this->full = ['4001' => ['Odeslána', null], '4002' => ['Odeslána', null], '4003' => ['Odeslána', null]];
        $this->shipments = [$this->shipment('4001', '2026-10-22'), $this->shipment('4002', '2026-10-22'), $this->shipment('4003', '2026-10-22')];
        $this->runEngine();

        // Upgates si CSV stáhl a 4001 přepnul, 4003 mezitím někdo vyřídil ručně.
        $this->get($this->automation->fresh()->csvUrl())->assertOk();
        $this->day = ['4001' => ['Čeká na vyzvednutí', '2026-10-27 11:05:00'], '4003' => ['Vyřešeno', '2026-10-27 11:10:00']];
        $this->travel(1)->hours();
        $this->runEngine();

        $this->assertSame(AutomationEvent::STATE_CONFIRMED, AutomationEvent::query()->where('order_number', '4001')->value('state'));
        $this->assertNotNull(AutomationEvent::query()->where('order_number', '4001')->value('confirmed_at'));
        $this->assertSame(AutomationEvent::STATE_SKIPPED, AutomationEvent::query()->where('order_number', '4003')->value('state'));
        $this->assertSame(AutomationEvent::STATE_PENDING, AutomationEvent::query()->where('order_number', '4002')->value('state'));
        Mail::assertNothingSent();

        $this->travel(3)->hours();
        $report = $this->runEngine();

        $this->assertSame(['4002'], $report['failed']);
        $failed = AutomationEvent::query()->where('order_number', '4002')->first();
        $this->assertSame(AutomationEvent::STATE_FAILED, $failed->state);
        $this->assertStringContainsString('stáhl, ale stav', $failed->detail);

        Mail::assertSent(AutomationProblem::class, 2);
        Mail::assertSent(AutomationProblem::class, fn (AutomationProblem $mail): bool => $mail->hasTo($admin->email));
        Mail::assertSent(AutomationProblem::class, fn (AutomationProblem $mail): bool => $mail->hasTo($marek->email) && str_contains($mail->problem, '4002'));
        Mail::assertNotSent(AutomationProblem::class, fn (AutomationProblem $mail): bool => $mail->hasTo($other->email) || $mail->hasTo('redaktor@taveo.cz'));
    }

    public function test_vypnuta_automatizace_nic_nezaklada_a_csv_ma_jen_hlavicku(): void
    {
        $this->full = ['5001' => ['Odeslána', null]];
        $this->shipments = [$this->shipment('5001', '2026-10-22')];
        $this->automation->update(['enabled' => false]);

        $this->runEngine();
        $this->artisan('automations:run')->assertSuccessful();

        $this->assertSame(0, AutomationEvent::query()->count());
        Http::assertNothingSent();

        AutomationEvent::query()->create(['automation_id' => $this->automation->id, 'order_number' => '5001', 'kind' => 'reminder', 'target_status' => 'Čeká na vyzvednutí']);
        $this->assertSame("[ORDER_NUMBER];[ORDER_STATUS]\r\n", $this->get($this->automation->csvUrl())->getContent());
    }

    public function test_csv_pro_upgates_ve_windows_1250_se_strednikem(): void
    {
        $event = fn (string $order, string $kind, string $status, string $state = 'pending') => AutomationEvent::query()->create([
            'automation_id' => $this->automation->id, 'order_number' => $order, 'kind' => $kind, 'target_status' => $status, 'state' => $state,
        ]);
        $event('04294', 'reminder', 'Čeká na vyzvednutí');
        $event('04295', 'last_day', 'Poslední den k vyzvednutí');
        $event('04296', 'reminder', 'Čeká na vyzvednutí', 'confirmed');

        $response = $this->get('/automatizace/'.$this->automation->import_token.'/upgates-import.csv');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=windows-1250')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $expected = "[ORDER_NUMBER];[ORDER_STATUS]\r\n04294;Čeká na vyzvednutí\r\n04295;Poslední den k vyzvednutí\r\n";
        $this->assertSame(iconv('UTF-8', 'Windows-1250', $expected), $response->getContent());
        $this->assertSame($expected, iconv('Windows-1250', 'UTF-8', $response->getContent()));
        $this->assertSame(2, AutomationEvent::query()->whereNotNull('exported_at')->count());

        $this->get('/automatizace/'.str_repeat('a', 40).'/upgates-import.csv')->assertNotFound();
        $this->get('/automatizace/kratky/upgates-import.csv')->assertNotFound();
    }

    public function test_engine_nezalozi_dva_radky_csv_pro_jednu_objednavku(): void
    {
        // Připomínka den předem ve 23:30, Upgates ji do půlnoci nestihne a v 0:40 je poslední den.
        $this->travelTo(CarbonImmutable::parse('2026-10-28 23:30:00', 'Europe/Prague'));
        $this->automation->update(['settings' => [...$this->automation->settings, 'remind_days_before' => 1]]);
        $this->full = ['6001' => ['Odeslána', null]];
        $this->shipments = [$this->shipment('6001', '2026-10-22')];
        $this->runEngine();
        $this->assertSame(['6001' => AutomationEvent::KIND_REMINDER], $this->events());

        $this->travel(70)->minutes();
        $this->runEngine();

        $this->assertSame(AutomationEvent::STATE_SKIPPED, AutomationEvent::query()->where('kind', 'reminder')->value('state'));
        $this->assertSame(AutomationEvent::STATE_PENDING, AutomationEvent::query()->where('kind', 'last_day')->value('state'));
        $lines = explode("\r\n", trim(iconv('Windows-1250', 'UTF-8', $this->get($this->automation->fresh()->csvUrl())->getContent())));
        $this->assertSame(['[ORDER_NUMBER];[ORDER_STATUS]', '6001;Poslední den k vyzvednutí'], $lines);
    }

    public function test_zaslat_se_strankuje_po_padesati(): void
    {
        foreach (range(1, 60) as $i) {
            $number = (string) (7000 + $i);
            $this->full[$number] = ['Odeslána', null];
            $this->shipments[] = $this->shipment($number, '2026-10-22');
        }

        $this->runEngine();

        $this->assertSame(60, AutomationEvent::query()->count());
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'offset=50') && $request->hasHeader('X-Apikey', 'tajny-klic-zaslat'));
    }

    public function test_exporty_se_slucuji_a_kompletni_se_stahuje_nejvys_jednou_za_6_hodin(): void
    {
        $this->full = ['8001' => ['Odeslána', '2026-10-26 08:00:00'], '8002' => ['Čeká na vyzvednutí', '2026-10-27 09:00:00'], '8003' => ['Odeslána', null]];
        $this->day = ['8001' => ['Vyřešeno', '2026-10-27 10:00:00'], '8002' => ['Odeslána', '2026-10-26 07:00:00'], '8003' => ['Stornována', null]];

        $orders = app(UpgatesOrders::class)->load($this->automation)['orders'];

        $this->assertSame('Vyřešeno', $orders['8001']['status']);              // novější z 24 h
        $this->assertSame('Čeká na vyzvednutí', $orders['8002']['status']);    // kompletní je novější
        $this->assertSame('Stornována', $orders['8003']['status']);            // bez času vyhraje pozdější adresa

        // Cache na disku bez osobních údajů.
        $cache = Storage::disk('local')->get('automatizace/'.$this->automation->id.'/objednavky.json');
        $this->assertStringNotContainsString('jana@example.cz', $cache);
        $this->assertStringNotContainsString('Jana', $cache);

        $this->travel(5)->hours();
        app(UpgatesOrders::class)->load($this->automation);
        $this->assertCount(1, Http::recorded(fn (Request $request): bool => $request->url() === self::FULL));
        $this->assertCount(2, Http::recorded(fn (Request $request): bool => $request->url() === self::DAY));

        // Kompletní nejde stáhnout, cache je mladší než 30 h → jen varování.
        $this->travel(2)->hours();
        $this->broken[self::FULL] = 500;
        $result = app(UpgatesOrders::class)->load($this->automation);
        $this->assertSame('Vyřešeno', $result['orders']['8001']['status']);
        $this->assertStringContainsString('použil se uložený', $result['warnings'][0]);

        // Starší než 30 h → chyba.
        $this->travel(30)->hours();
        $this->expectException(AutomationException::class);
        app(UpgatesOrders::class)->load($this->automation);
    }

    public function test_chyba_24h_exportu_ukonci_beh_nic_nezmeni_a_upozorni_jednou_za_6_hodin(): void
    {
        Mail::fake();
        User::factory()->create(['role' => UserRole::Admin]);
        $this->full = ['9001' => ['Odeslána', null]];
        $this->shipments = [$this->shipment('9001', '2026-10-22')];
        $this->broken[self::DAY] = 503;

        $this->artisan('automations:run')->assertFailed();

        $this->assertSame(0, AutomationEvent::query()->count());
        $this->assertStringContainsString('HTTP 503', $this->automation->fresh()->last_error);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'zaslat.cz'));
        Mail::assertSent(AutomationProblem::class, 1);

        $this->travel(1)->hours();
        $this->runEngine();
        Mail::assertSent(AutomationProblem::class, 1);

        $this->travel(6)->hours();
        $this->runEngine();
        Mail::assertSent(AutomationProblem::class, 2);

        unset($this->broken[self::DAY]);
        $this->runEngine();
        $this->assertNull($this->automation->fresh()->last_error);
        $this->assertSame(1, AutomationEvent::query()->count());
    }

    public function test_bez_klice_k_zaslatu_toho_klienta_bezi_s_chybou(): void
    {
        $foreign = VaultEntry::query()->create([
            'client_id' => Client::create(['name' => 'Cizí', 'slug' => 'cizi'])->id,
            'type' => VaultType::ApiKey, 'name' => 'Cizí Zaslat', 'url' => 'zaslat.cz', 'secret' => 'x', 'agent_access' => 'none',
        ]);
        $this->automation->update(['settings' => [...$this->automation->settings, 'vault_entry_id' => $foreign->id]]);

        $report = $this->runEngine();

        $this->assertStringContainsString('Chybí přístup k Zaslatu', $report['error']);
    }

    public function test_nanecisto_nic_neulozi(): void
    {
        $this->full = ['9101' => ['Odeslána', null]];
        $this->shipments = [$this->shipment('9101', '2026-10-22')];

        $this->artisan('automations:run', ['--dry-run' => true])
            ->expectsOutputToContain('9101: Připomínka vyzvednutí (e-mail)')
            ->assertSuccessful();

        $this->assertSame(0, AutomationEvent::query()->count());
        $this->assertNull($this->automation->fresh()->last_run_at);
        Storage::disk('local')->assertMissing('automatizace/'.$this->automation->id.'/objednavky.json');
    }

    public function test_parser_ulozni_doby_zasilkovny(): void
    {
        $this->assertSame('2026-11-03', PacketaTracking::parse(self::packetaHtml('2026-11-03')));

        // Bez __NUXT_DATA__: první datum za textem stavu (úryvek ze skutečné stránky).
        $snippet = '<script>window.x=[,"Zásilka je připravena k vyzvednutí","2","2026-11-03","2026-11-18 00:00:00",true,"Svět Cejlonu",]</script>';
        $this->assertSame('2026-11-03', PacketaTracking::parse($snippet));

        $this->assertSame('2026-11-04', PacketaTracking::parse('{"storedUntil":"2026-11-04T23:59:59"}'));
        $this->assertNull(PacketaTracking::parse('<html>Zásilka je na cestě</html>'));
        $this->assertNull(PacketaTracking::parse(self::packetaHtml('2026-13-45')));

        $this->assertTrue(PacketaTracking::allowed('https://tracking.packeta.com/cs/?id=Z123'));
        $this->assertFalse(PacketaTracking::allowed('https://packeta.com.evil.cz/'));
        $this->assertFalse(PacketaTracking::allowed('http://tracking.packeta.com/'));
    }

    public function test_parser_exportu_upgates(): void
    {
        $sample = '<?xml version="1.0" encoding="utf-8"?><ORDERS version="1.0">'
            .'<ORDER><HEADER><CODE>04294</CODE><STATUS>Odeslána </STATUS><UPDATED_AT>2026-10-27 09:00:00</UPDATED_AT></HEADER>'
            .'<CUSTOMER><EMAIL>a@b.cz</EMAIL></CUSTOMER><AGREEMENTS><AGREEMENT><STATUS>1</STATUS></AGREEMENT></AGREEMENTS></ORDER>'
            .'<ORDER><ORDER_NUMBER>04295</ORDER_NUMBER><STATUS>Odeslat znovu až se vrátí </STATUS></ORDER>'
            .'<ORDER><STATUS>Bez čísla</STATUS></ORDER>'
            ."\n<ORDER>\n<ORDER_NUMBER>04296</ORDER_NUMBER>\n<STATUS>Vráceno</STATUS>\n</ORDER>"
            .'</ORDERS>';

        $orders = UpgatesOrders::parse($sample);

        $this->assertSame(['04294', '04295', '04296'], array_map('strval', array_keys($orders)));
        $this->assertSame('Odeslána', $orders['04294']['status']);
        $this->assertSame('Odeslat znovu až se vrátí', $orders['04295']['status']);
        $this->assertNotNull($orders['04294']['updated_at']);
        $this->assertNull($orders['04295']['updated_at']);

        $this->expectException(AutomationException::class);
        UpgatesOrders::parse('<html>Přihlaste se</html>');
    }

    public function test_ranni_souhrn_pro_klienta(): void
    {
        Mail::fake();
        $marek = User::factory()->create(['role' => UserRole::Client, 'client_id' => $this->client->id]);
        User::factory()->create(['role' => UserRole::Admin]);
        $this->automation->update(['snapshot' => ['parcels' => [['order' => '1'], ['order' => '2'], ['order' => '3']]]]);

        $yesterday = now()->subDay();
        foreach (['1', '2'] as $order) {
            AutomationEvent::query()->create(['automation_id' => $this->automation->id, 'order_number' => $order, 'kind' => 'reminder', 'target_status' => 'x', 'state' => 'confirmed', 'confirmed_at' => $yesterday]);
        }
        AutomationEvent::query()->create(['automation_id' => $this->automation->id, 'order_number' => '3', 'kind' => 'last_day', 'target_status' => 'x', 'state' => 'confirmed', 'confirmed_at' => now()]);

        $this->artisan('automations:summary')->assertSuccessful();

        Mail::assertSent(AutomationSummary::class, 1);
        Mail::assertSent(AutomationSummary::class, fn (AutomationSummary $mail): bool => $mail->hasTo($marek->email)
            && $mail->sent === [AutomationEvent::kindLabel('reminder') => 2]
            && $mail->orders === [AutomationEvent::kindLabel('reminder') => ['1', '2']]
            && $mail->parcels === 3);

        $this->automation->update(['enabled' => false]);
        $this->artisan('automations:summary')->assertSuccessful();
        Mail::assertSent(AutomationSummary::class, 1);
    }
}
