<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\VaultType;
use App\Filament\Tools\Resources\VaultEntries\Pages\CreateVaultEntry;
use App\Filament\Tools\Resources\VaultEntries\Pages\EditVaultEntry;
use App\Filament\Tools\Resources\VaultEntries\Pages\ListVaultEntries;
use App\Filament\Tools\Resources\VaultEntries\RelationManagers\UploadsRelationManager;
use App\Models\Client;
use App\Models\User;
use App\Models\VaultEntry;
use App\Models\VaultLog;
use App\Models\VaultUpload;
use App\Support\Vault\FtpClient;
use App\Support\Vault\FtpConnector;
use App\Support\Vault\LoginBroker;
use App\Support\Vault\VaultLock;
use App\Support\Vault\VaultPath;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

class VaultTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'agent-token-agent-token-agent-token-1234';

    private InMemoryFtp $ftp;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.agent.api_token' => self::TOKEN]);
        Storage::fake('local');

        $this->ftp = new InMemoryFtp([
            'index.php' => '<?php // web',
            'wp-config.php' => '<?php define("DB_PASSWORD", "x");',
            'wp-content/themes/pizza/functions.php' => '<?php create_function("", "");',
            'wp-content/uploads/2026/foto.jpg' => 'JPG',
        ]);
        $ftp = $this->ftp;
        $this->app->instance(FtpConnector::class, new class($ftp) extends FtpConnector
        {
            public function __construct(private FtpClient $ftp) {}

            public function open(VaultEntry $entry): FtpClient
            {
                return $this->ftp;
            }
        });

        $this->client = Client::create(['name' => 'Pizza di Gomma', 'slug' => 'pizza-di-gomma']);
    }

    private function agent(): array
    {
        return ['Authorization' => 'Bearer '.self::TOKEN];
    }

    private function ftpEntry(string $access = 'approve'): VaultEntry
    {
        return VaultEntry::query()->create([
            'client_id' => $this->client->getKey(),
            'type' => VaultType::Ftp,
            'name' => 'FTP webu',
            'url' => 'ftp.pizzadigomma.eu',
            'username' => 'pizza',
            'secret' => 'tajne-ftp-heslo',
            'notes' => 'Dal nám ho majitel',
            'settings' => ['port' => 21, 'tls' => true, 'root' => '/www'],
            'agent_access' => $access,
        ]);
    }

    public function test_hesla_jsou_v_databazi_zasifrovana_a_agent_je_nedostane(): void
    {
        $entry = $this->ftpEntry();
        VaultEntry::query()->create(['type' => VaultType::ApiKey, 'name' => 'Klíč jen pro lidi', 'secret' => 'klic', 'agent_access' => 'none']);

        $raw = DB::table('vault_entries')->where('id', $entry->getKey())->first();
        $this->assertNotSame('tajne-ftp-heslo', $raw->secret);
        $this->assertStringNotContainsString('tajne-ftp-heslo', $raw->secret);
        $this->assertStringNotContainsString('majitel', $raw->notes);
        $this->assertSame('tajne-ftp-heslo', $entry->fresh()->secret);

        $response = $this->getJson('/nastroje/api/agent/trezor?klient=pizza-di-gomma', $this->agent())->assertOk();

        $response->assertJsonCount(1, 'pristupy')
            ->assertJsonPath('pristupy.0.nazev', 'FTP webu')
            ->assertJsonPath('pristupy.0.slozka', '/www');
        $this->assertStringNotContainsString('tajne-ftp-heslo', $response->getContent());
        $this->assertStringNotContainsString('majitel', $response->getContent());
        $this->assertStringNotContainsString('tajne-ftp-heslo', json_encode($entry->toArray()));
    }

    public function test_bez_tokenu_agenta_trezor_neodpovida(): void
    {
        $this->ftpEntry();

        $this->getJson('/nastroje/api/agent/trezor')->assertUnauthorized();
        $this->getJson('/nastroje/api/agent/trezor', ['Authorization' => 'Bearer spatny-token-spatny-token-spatny-12345'])->assertUnauthorized();
    }

    public function test_agent_cte_ftp_a_stahne_slozku_bez_nahranych_obrazku(): void
    {
        $entry = $this->ftpEntry('read');

        $this->getJson("/nastroje/api/agent/trezor/{$entry->id}/ftp?cesta=wp-content", $this->agent())
            ->assertOk()
            ->assertJsonPath('polozky.0.name', 'themes');

        $this->get("/nastroje/api/agent/trezor/{$entry->id}/ftp/soubor?cesta=wp-content/themes/pizza/functions.php", $this->agent())
            ->assertOk()
            ->assertContent('<?php create_function("", "");');

        $this->getJson("/nastroje/api/agent/trezor/{$entry->id}/ftp/soubor?cesta=../etc/passwd", $this->agent())
            ->assertStatus(422)
            ->assertJsonPath('chyba', 'Cesta nesmí vést ven ze složky webu (..).');

        $zip = $this->get("/nastroje/api/agent/trezor/{$entry->id}/ftp/zip", $this->agent())->assertOk();
        $file = tempnam(sys_get_temp_dir(), 'zip');
        file_put_contents($file, $zip->streamedContent());
        $archive = new ZipArchive;
        $archive->open($file);

        $this->assertNotFalse($archive->locateName('wp-content/themes/pizza/functions.php'));
        $this->assertFalse($archive->locateName('wp-content/uploads/2026/foto.jpg'));
        $this->assertStringContainsString('wp-content/uploads/', $archive->getFromName('_vynechano.txt'));
        $archive->close();
        unlink($file);

        $this->assertSame(['ftp_list', 'ftp_read', 'ftp_zip'], $entry->logs()->orderBy('id')->pluck('action')->all());
    }

    public function test_agent_bez_prava_nenahraje_a_odmitnuti_je_v_historii(): void
    {
        $entry = $this->ftpEntry('read');

        $this->post("/nastroje/api/agent/trezor/{$entry->id}/ftp/nahrat", [
            'soubory' => [UploadedFile::fake()->createWithContent('functions.php', '<?php // opraveno')],
            'cesty' => ['wp-content/themes/pizza/functions.php'],
        ], $this->agent())->assertForbidden();

        $this->assertSame('<?php create_function("", "");', $this->ftp->files['wp-content/themes/pizza/functions.php']);
        $this->assertSame('denied', $entry->logs()->value('action'));
    }

    public function test_nahrani_ceka_na_schvaleni_pak_jde_na_web_a_jde_vratit(): void
    {
        $entry = $this->ftpEntry('approve');

        $this->post("/nastroje/api/agent/trezor/{$entry->id}/ftp/nahrat", [
            'popis' => 'Náhrada create_function pro PHP 8',
            'soubory' => [
                UploadedFile::fake()->createWithContent('functions.php', '<?php // opraveno'),
                UploadedFile::fake()->createWithContent('compat.php', '<?php // nový'),
            ],
            'cesty' => ['wp-content/themes/pizza/functions.php', 'wp-content/themes/pizza/compat.php'],
        ], $this->agent())
            ->assertOk()
            ->assertJsonPath('stav', 'Čeká na schválení');

        $this->assertSame('<?php create_function("", "");', $this->ftp->files['wp-content/themes/pizza/functions.php']);

        $this->actingAs(User::factory()->create(['role' => UserRole::Admin, 'name' => 'Tom']));
        Filament::setCurrentPanel('tools');
        $upload = VaultUpload::query()->sole();

        Livewire::test(UploadsRelationManager::class, ['ownerRecord' => $entry, 'pageClass' => EditVaultEntry::class])
            ->callTableAction('apply', $upload);

        $this->assertSame('<?php // opraveno', $this->ftp->files['wp-content/themes/pizza/functions.php']);
        $this->assertSame('<?php // nový', $this->ftp->files['wp-content/themes/pizza/compat.php']);
        $this->assertSame(VaultUpload::STATUS_APPLIED, $upload->fresh()->status);
        $this->assertSame('Tom', $upload->fresh()->decided_by);

        Livewire::test(UploadsRelationManager::class, ['ownerRecord' => $entry, 'pageClass' => EditVaultEntry::class])
            ->callTableAction('revert', $upload);

        $this->assertSame('<?php create_function("", "");', $this->ftp->files['wp-content/themes/pizza/functions.php']);
        $this->assertArrayNotHasKey('wp-content/themes/pizza/compat.php', $this->ftp->files);
        $this->assertSame(VaultUpload::STATUS_REVERTED, $upload->fresh()->status);
        $this->assertSame(
            ['upload_requested', 'upload_applied', 'upload_reverted'],
            $entry->logs()->orderBy('id')->pluck('action')->all(),
        );
    }

    public function test_s_pravem_nahravat_hned_jde_soubor_rovnou_na_web(): void
    {
        $entry = $this->ftpEntry('write');

        $this->post("/nastroje/api/agent/trezor/{$entry->id}/ftp/nahrat", [
            'soubory' => [UploadedFile::fake()->createWithContent('index.php', '<?php // nový index')],
            'cesty' => ['index.php'],
        ], $this->agent())
            ->assertOk()
            ->assertJsonPath('stav', 'Nahráno na web');

        $this->assertSame('<?php // nový index', $this->ftp->files['index.php']);
        $this->assertSame('<?php // web', Storage::disk('local')->get('trezor/'.VaultUpload::query()->value('id').'/puvodni/index.php'));
    }

    public function test_wordpress_cte_s_aplikacnim_heslem_a_uzivatele_nezmeni(): void
    {
        Http::fake(['pizzadigomma.eu/wp-json/*' => Http::response([['plugin' => 'woocommerce/woocommerce', 'version' => '10.9.3']])]);

        $entry = VaultEntry::query()->create([
            'client_id' => $this->client->getKey(),
            'type' => VaultType::WordPress,
            'name' => 'WordPress',
            'url' => 'https://pizzadigomma.eu',
            'username' => 'taveo-agent',
            'app_password' => 'abcd efgh ijkl mnop',
            'agent_access' => 'read',
        ]);

        $this->postJson("/nastroje/api/agent/trezor/{$entry->id}/wp", ['cesta' => '/wp/v2/plugins'], $this->agent())
            ->assertOk()
            ->assertJsonPath('0.version', '10.9.3');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://pizzadigomma.eu/wp-json/wp/v2/plugins'
            && $request->header('Authorization')[0] === 'Basic '.base64_encode('taveo-agent:abcdefghijklmnop'));

        $this->postJson("/nastroje/api/agent/trezor/{$entry->id}/wp", ['metoda' => 'POST', 'cesta' => '/wp/v2/plugins/akismet/akismet', 'data' => ['status' => 'active']], $this->agent())
            ->assertStatus(422)
            ->assertJsonPath('chyba', 'U tohoto přístupu smí agent WordPress jen číst. Změnit to jde v trezoru („Co smí agent“).');

        $entry->update(['agent_access' => 'write']);

        $this->postJson("/nastroje/api/agent/trezor/{$entry->id}/wp", ['metoda' => 'POST', 'cesta' => '/wp/v2/users/1', 'data' => ['password' => 'x']], $this->agent())
            ->assertStatus(422);
        Http::assertSentCount(1);
    }

    public function test_wordpress_posle_parametry_z_cesty_a_obejit_pravidla_nejde(): void
    {
        Http::fake(['pizzadigomma.eu/wp-json/*' => Http::response([])]);

        $entry = VaultEntry::query()->create([
            'type' => VaultType::WordPress,
            'name' => 'WordPress',
            'url' => 'https://pizzadigomma.eu',
            'username' => 'taveo-agent',
            'app_password' => 'abcd efgh ijkl mnop',
            'agent_access' => 'write',
        ]);

        $this->postJson("/nastroje/api/agent/trezor/{$entry->id}/wp", ['cesta' => '/wp/v2/posts?per_page=100&page=2', 'dotaz' => ['page' => 3]], $this->agent())
            ->assertOk();

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://pizzadigomma.eu/wp-json/wp/v2/posts?per_page=100&page=3');

        foreach ([
            ['cesta' => '/wp/v2/posts?_method=DELETE'],
            ['cesta' => '/wp/v2/posts', 'dotaz' => ['rest_route' => '/wp/v2/users/1']],
            ['metoda' => 'POST', 'cesta' => '/wp/v2/Users/1', 'data' => ['password' => 'x']],
            ['metoda' => 'POST', 'cesta' => '/wp/v2/%75sers/1', 'data' => ['password' => 'x']],
        ] as $payload) {
            $this->postJson("/nastroje/api/agent/trezor/{$entry->id}/wp", $payload, $this->agent())->assertStatus(422);
        }

        Http::assertSentCount(1);
    }

    public function test_zaslat_cte_s_klicem_z_trezoru_a_zasilky_nezalozi(): void
    {
        Http::fake(['www.zaslat.cz/api/v1/*' => Http::response(['status' => 200, 'data' => []])]);

        $entry = VaultEntry::query()->create([
            'client_id' => $this->client->getKey(),
            'type' => VaultType::ApiKey,
            'name' => 'Zaslat API',
            'url' => 'zaslat.cz',
            'secret' => 'tajny-klic-zaslat',
            'agent_access' => 'read',
        ]);

        $this->getJson('/nastroje/api/agent/trezor', $this->agent())
            ->assertOk()
            ->assertJsonPath('pristupy.0.umi.0', 'taveo zaslat <id> GET "/shipments/list?tracking&status=ACTIVE"')
            ->assertDontSee('tajny-klic-zaslat');

        $this->postJson("/nastroje/api/agent/trezor/{$entry->id}/zaslat", ['cesta' => '/shipments/list?tracking&status=ACTIVE'], $this->agent())
            ->assertOk()
            ->assertJsonPath('status', 200);

        $this->postJson("/nastroje/api/agent/trezor/{$entry->id}/zaslat", ['metoda' => 'POST', 'cesta' => '/api/v1/shipments/tracking', 'data' => ['shipments' => ['ZA123']]], $this->agent())
            ->assertOk();

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://www.zaslat.cz/api/v1/shipments/list?tracking=&status=ACTIVE'
            && $request->header('X-Apikey')[0] === 'tajny-klic-zaslat');
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://www.zaslat.cz/api/v1/shipments/tracking'
            && $request['shipments'] === ['ZA123']);

        foreach ([
            ['metoda' => 'POST', 'cesta' => '/shipments/create', 'data' => ['shipments' => []]],
            ['metoda' => 'POST', 'cesta' => '/shipments/cancel'],
            ['metoda' => 'DELETE', 'cesta' => '/shipments/ZA123'],
            ['metoda' => 'POST', 'cesta' => '/shipments/tracking/../create'],
        ] as $payload) {
            $this->postJson("/nastroje/api/agent/trezor/{$entry->id}/zaslat", $payload, $this->agent())->assertStatus(422);
        }

        Http::assertSentCount(2);
        $this->assertDatabaseHas('vault_logs', ['vault_entry_id' => $entry->id, 'action' => 'zaslat_api']);
    }

    public function test_jiny_api_klic_agent_nepouzije_a_bez_prava_ani_zaslat(): void
    {
        Http::fake();

        $other = VaultEntry::query()->create([
            'type' => VaultType::ApiKey,
            'name' => 'Klíč k API Upgates',
            'url' => 'https://api.upgates.com',
            'secret' => 'klic',
            'agent_access' => 'read',
        ]);
        $hidden = VaultEntry::query()->create([
            'type' => VaultType::ApiKey,
            'name' => 'Zaslat jen pro lidi',
            'url' => 'https://www.zaslat.cz',
            'secret' => 'klic',
            'agent_access' => 'none',
        ]);
        $evil = VaultEntry::query()->create([
            'type' => VaultType::ApiKey,
            'name' => 'Podvržená adresa',
            'url' => 'https://zaslat.cz.utocnik.example',
            'secret' => 'klic',
            'agent_access' => 'read',
        ]);

        $this->postJson("/nastroje/api/agent/trezor/{$other->id}/zaslat", ['cesta' => '/shipments/list'], $this->agent())->assertStatus(422);
        $this->postJson("/nastroje/api/agent/trezor/{$evil->id}/zaslat", ['cesta' => '/shipments/list'], $this->agent())->assertStatus(422);
        $this->postJson("/nastroje/api/agent/trezor/{$hidden->id}/zaslat", ['cesta' => '/shipments/list'], $this->agent())->assertForbidden();
        $this->assertSame([], $other->agentCommands());

        Http::assertNothingSent();
    }

    public function test_ecomail_jen_cte_s_klicem_z_trezoru(): void
    {
        Http::fake(['api2.ecomailapp.cz/*' => Http::response([['id' => 1, 'name' => 'Newsletter']])]);

        $entry = VaultEntry::query()->create([
            'client_id' => $this->client->getKey(),
            'type' => VaultType::ApiKey,
            'name' => 'API ecomail',
            'url' => 'ecomail.cz',
            'secret' => 'tajny-klic-ecomail',
            'agent_access' => 'read',
        ]);

        $this->getJson('/nastroje/api/agent/trezor', $this->agent())
            ->assertOk()
            ->assertJsonPath('pristupy.0.umi.0', 'taveo ecomail <id> GET /lists')
            ->assertDontSee('tajny-klic-ecomail');

        $this->postJson("/nastroje/api/agent/trezor/{$entry->id}/ecomail", ['metoda' => 'GET', 'cesta' => '/lists'], $this->agent())
            ->assertOk()
            ->assertJsonPath('0.name', 'Newsletter');

        $this->postJson("/nastroje/api/agent/trezor/{$entry->id}/ecomail", ['cesta' => '/lists/1/subscribers?page=2', 'dotaz' => ['per_page' => 50]], $this->agent())
            ->assertOk();

        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->url() === 'https://api2.ecomailapp.cz/lists'
            && $request->header('key')[0] === 'tajny-klic-ecomail');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api2.ecomailapp.cz/lists/1/subscribers?page=2&per_page=50');

        foreach ([
            ['metoda' => 'POST', 'cesta' => '/campaigns/1/send'],
            ['metoda' => 'DELETE', 'cesta' => '/lists/1/unsubscribe'],
            ['metoda' => 'PUT', 'cesta' => '/lists/1/update-subscriber'],
        ] as $payload) {
            $this->postJson("/nastroje/api/agent/trezor/{$entry->id}/ecomail", $payload, $this->agent())->assertForbidden();
        }

        foreach ([
            ['cesta' => '/lists/../campaigns'],
            ['cesta' => 'https://utocnik.example/lists'],
            ['cesta' => '/'],
            // Ecomail odesílá kampaň a obnovuje feedy přes GET.
            ['cesta' => '/campaign/5/send'],
            ['cesta' => '//Campaign/5/Send/'],
            ['cesta' => '/%63ampaign/5/send'],
            ['cesta' => '/campaigns/5/send'],
            ['cesta' => '/feeds/3/refresh'],
            ['cesta' => '/data-feeds/3/refresh'],
            ['cesta' => '/subscribers/a@b.cz/delete'],
        ] as $payload) {
            $this->postJson("/nastroje/api/agent/trezor/{$entry->id}/ecomail", $payload, $this->agent())->assertStatus(422);
        }

        Http::assertSentCount(2);
        $this->assertSame(2, VaultLog::query()->where('vault_entry_id', $entry->id)->where('action', 'ecomail_api')->count());
    }

    public function test_ecomail_meni_jen_s_pravem_a_nic_neodesle(): void
    {
        Http::fake(['api2.ecomailapp.cz/*' => Http::response(['id' => 1])]);

        $entry = VaultEntry::query()->create([
            'client_id' => $this->client->getKey(),
            'type' => VaultType::ApiKey,
            'name' => 'API ecomail',
            'url' => 'ecomail.cz',
            'secret' => 'tajny-klic-ecomail',
            'agent_access' => 'read',
        ]);
        $url = "/nastroje/api/agent/trezor/{$entry->id}/ecomail";

        // Jen čtení: změna neprojde a zapíše se jako odmítnutá.
        $this->postJson($url, ['metoda' => 'PUT', 'cesta' => '/campaigns/5', 'data' => ['subject' => 'Nový předmět']], $this->agent())->assertForbidden();
        $this->assertDatabaseHas('vault_logs', ['vault_entry_id' => $entry->id, 'action' => 'denied']);
        Http::assertNothingSent();

        $entry->update(['agent_access' => 'write']);
        $this->assertContains('taveo ecomail <id> POST|PUT|DELETE <cesta> \'{"json"}\'', $entry->fresh()->agentCommands());

        $this->postJson($url, ['metoda' => 'PUT', 'cesta' => '/campaigns/5', 'data' => ['subject' => 'Nový předmět', 'html_text' => '<p>Ahoj</p>']], $this->agent())->assertOk();
        $this->postJson($url, ['metoda' => 'POST', 'cesta' => '/lists/3/subscribe', 'data' => ['subscriber_data' => ['email' => 'a@b.cz'], 'trigger_autoresponders' => true, 'skip_confirmation' => false, 'resubscribe' => true]], $this->agent())->assertOk();
        $this->postJson($url, ['metoda' => 'DELETE', 'cesta' => '/lists/3/unsubscribe', 'data' => ['email' => 'a@b.cz']], $this->agent())->assertOk();

        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && $request->url() === 'https://api2.ecomailapp.cz/campaigns/5'
            && $request['subject'] === 'Nový předmět'
            && $request->header('key')[0] === 'tajny-klic-ecomail');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api2.ecomailapp.cz/lists/3/subscribe'
            && $request['trigger_autoresponders'] === false
            && $request['trigger_notification'] === false
            && $request['resubscribe'] === false
            && $request['skip_confirmation'] === true);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && $request->url() === 'https://api2.ecomailapp.cz/lists/3/unsubscribe');

        foreach ([
            ['metoda' => 'GET', 'cesta' => '/campaign/5/send'],
            ['metoda' => 'POST', 'cesta' => '/campaign/5/send'],
            ['metoda' => 'POST', 'cesta' => '/pipelines/2/trigger'],
            ['metoda' => 'DELETE', 'cesta' => '/subscribers/a@b.cz/delete'],
            ['metoda' => 'POST', 'cesta' => '/transactional/send-message', 'data' => ['message' => []]],
            ['metoda' => 'DELETE', 'cesta' => '/domains/1'],
            ['metoda' => 'POST', 'cesta' => '/account/settings/webhook', 'data' => ['url' => 'https://utocnik.example']],
            ['metoda' => 'PUT', 'cesta' => '/campaigns/5', 'data' => ['scheduled_at' => '2026-11-01 08:00']],
            ['metoda' => 'POST', 'cesta' => '/lists/3/subscribe', 'data' => ['subscriber_data' => ['email' => 'a@b.cz', 'status' => 1]]],
            ['metoda' => 'POST', 'cesta' => '/lists/3/subscribe-bulk', 'data' => ['subscriber_data' => [['email' => 'a@b.cz'], ['email' => 'c@d.cz', 'status' => 1]]]],
            ['metoda' => 'PUT', 'cesta' => '/lists/3/update-subscriber', 'data' => ['email' => 'a@b.cz', 'subscriber_data' => ['status' => 1]]],
        ] as $payload) {
            $this->postJson($url, $payload, $this->agent())->assertStatus(422);
        }

        Http::assertSentCount(3);
        $this->assertSame(3, VaultLog::query()->where('vault_entry_id', $entry->id)->where('action', 'ecomail_change')->count());
    }

    public function test_vyzkouset_ecomail_zavola_seznamy(): void
    {
        Http::fake(['api2.ecomailapp.cz/lists' => Http::response([['id' => 1], ['id' => 2]])]);
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin, 'name' => 'Tom']));
        Filament::setCurrentPanel('tools');

        $entry = VaultEntry::query()->create([
            'type' => VaultType::ApiKey,
            'name' => 'API ecomail',
            'url' => 'ecomail.cz',
            'secret' => 'klic',
            'agent_access' => 'read',
        ]);

        Livewire::test(EditVaultEntry::class, ['record' => $entry->getKey()])
            ->callAction('test')
            ->assertNotified('Funguje');

        $this->assertDatabaseHas('vault_logs', ['vault_entry_id' => $entry->id, 'action' => 'tested', 'detail' => 'Klíč k Ecomailu funguje, seznamů kontaktů: 2.']);
    }

    public function test_ecomail_jen_s_adresou_ecomailu_a_s_pravem(): void
    {
        Http::fake();

        $zaslat = VaultEntry::query()->create([
            'type' => VaultType::ApiKey,
            'name' => 'Zaslat API',
            'url' => 'zaslat.cz',
            'secret' => 'klic',
            'agent_access' => 'read',
        ]);
        $evil = VaultEntry::query()->create([
            'type' => VaultType::ApiKey,
            'name' => 'Podvržená adresa',
            'url' => 'https://ecomail.cz.utocnik.example',
            'secret' => 'klic',
            'agent_access' => 'read',
        ]);
        $hidden = VaultEntry::query()->create([
            'type' => VaultType::ApiKey,
            'name' => 'Ecomail jen pro lidi',
            'url' => 'https://ecomail.cz/',
            'secret' => 'klic',
            'agent_access' => 'none',
        ]);
        $login = VaultEntry::query()->create([
            'type' => VaultType::Web,
            'name' => 'Ecomail administrace',
            'url' => 'https://ecomail.cz/',
            'username' => 'tym@taveo.cz',
            'secret' => 'heslo',
            'agent_access' => 'read',
        ]);
        $ecomail = VaultEntry::query()->create([
            'type' => VaultType::ApiKey,
            'name' => 'Ecomail API',
            'url' => 'https://ucet.ecomailapp.cz',
            'secret' => 'klic',
            'agent_access' => 'read',
        ]);

        $this->postJson("/nastroje/api/agent/trezor/{$zaslat->id}/ecomail", ['cesta' => '/lists'], $this->agent())->assertStatus(422);
        $this->postJson("/nastroje/api/agent/trezor/{$evil->id}/ecomail", ['cesta' => '/lists'], $this->agent())->assertStatus(422);
        $this->postJson("/nastroje/api/agent/trezor/{$login->id}/ecomail", ['cesta' => '/lists'], $this->agent())->assertStatus(422);
        $this->postJson("/nastroje/api/agent/trezor/{$hidden->id}/ecomail", ['cesta' => '/lists'], $this->agent())->assertForbidden();
        $this->postJson("/nastroje/api/agent/trezor/{$ecomail->id}/zaslat", ['cesta' => '/shipments/list'], $this->agent())->assertStatus(422);
        $this->assertSame(['taveo ecomail <id> GET /lists'], $ecomail->agentCommands());
        $this->assertSame([], $evil->agentCommands());

        Http::assertNothingSent();
    }

    public function test_prihlaseni_do_shoptetu_vrati_jen_cookies(): void
    {
        Http::fake([
            'https://www.eshop.cz/admin/login/' => Http::sequence()
                ->push('<form action="https://www.eshop.cz/admin/login/" method="post"><input type="hidden" name="action" value="login"><input type="email" name="email"><input type="password" name="password"></form>'
                    .'<script>shoptet.csrf.token = "csrf_abc123";</script>', 200, ['Set-Cookie' => 'PHPSESSID=pred; path=/; domain=www.eshop.cz'])
                ->push('<h1>Přehled</h1>', 200, ['Set-Cookie' => 'PHPSESSID=prihlasen; path=/; domain=www.eshop.cz; HttpOnly']),
        ]);

        $entry = VaultEntry::query()->create([
            'client_id' => $this->client->getKey(),
            'type' => VaultType::Shoptet,
            'name' => 'Shoptet',
            'url' => 'https://www.eshop.cz',
            'username' => 'agent@taveo.cz',
            'secret' => 'shoptet-heslo',
            'agent_access' => 'read',
        ]);

        $response = $this->postJson("/nastroje/api/agent/trezor/{$entry->id}/prihlasit", [], $this->agent())
            ->assertOk()
            ->assertJsonPath('adresa', 'https://www.eshop.cz/admin/')
            ->assertJsonPath('cookies.0.value', 'prihlasen');

        $this->assertStringNotContainsString('shoptet-heslo', $response->getContent());
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request['email'] === 'agent@taveo.cz' && $request['password'] === 'shoptet-heslo' && $request['action'] === 'login'
            && $request['__csrf__'] === 'csrf_abc123');
        $this->assertSame('login', $entry->logs()->value('action'));
    }

    public function test_prihlaseni_do_shoptetu_si_pamatuje_zarizeni_a_relaci(): void
    {
        $form = '<form action="https://www.eshop.cz/admin/login/" method="post"><input type="email" name="email"><input type="password" name="password"></form>';
        $device = 'fingerprint=zarizeni-1; expires='.gmdate('D, d M Y H:i:s', time() + 180 * 86400).' GMT; path=/; domain=www.eshop.cz; HttpOnly';
        $logins = 0;

        Http::fake(function (Request $request) use ($form, $device, &$logins) {
            $cookie = $request->header('Cookie')[0] ?? '';

            return match (true) {
                $request->url() === 'https://www.eshop.cz/admin/' => str_contains($cookie, 'PHPSESSID=prihlasen-'.$logins) && $logins > 0
                    ? Http::response('<h1>Přehled</h1>')
                    : Http::response($form),
                $request->method() === 'POST' => Http::response('<h1>Přehled</h1>', 200, ['Set-Cookie' => [
                    'PHPSESSID=prihlasen-'.(++$logins).'; path=/; domain=www.eshop.cz; HttpOnly',
                    ...str_contains($cookie, 'fingerprint=zarizeni-1') ? [] : [$device],
                ]]),
                default => Http::response($form, 200, ['Set-Cookie' => 'PHPSESSID=pred; path=/; domain=www.eshop.cz']),
            };
        });

        $entry = VaultEntry::query()->create([
            'client_id' => $this->client->getKey(),
            'type' => VaultType::Shoptet,
            'name' => 'Shoptet',
            'url' => 'https://www.eshop.cz',
            'username' => 'agent@taveo.cz',
            'secret' => 'shoptet-heslo',
            'agent_access' => 'read',
        ]);

        // Poprvé nové zařízení: web nastaví fingerprint a trezor si ho uloží (šifrovaně).
        $this->postJson("/nastroje/api/agent/trezor/{$entry->id}/prihlasit", [], $this->agent())->assertOk();
        $stored = DB::table('vault_entries')->where('id', $entry->id)->value('login_cookies');
        $this->assertStringNotContainsString('zarizeni-1', $stored);
        $this->assertContains('fingerprint', array_column($entry->refresh()->login_cookies, 'Name'));

        // Relace platí: žádné nové přihlášení, agent dostane tutéž.
        $this->postJson("/nastroje/api/agent/trezor/{$entry->id}/prihlasit", [], $this->agent())
            ->assertOk()
            ->assertJsonPath('adresa', 'https://www.eshop.cz/admin/');
        $this->assertSame(1, $logins);
        $this->assertSame('Platná relace z minula, bez nového přihlášení', $entry->logs()->latest('id')->value('detail'));

        // Relace vypršela: přihlásí se znovu se známým zařízením, starou relaci neposílá.
        $logins = 5;
        $response = $this->postJson("/nastroje/api/agent/trezor/{$entry->id}/prihlasit", [], $this->agent())->assertOk();
        $this->assertSame(6, $logins);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_contains($request->header('Cookie')[0] ?? '', 'fingerprint=zarizeni-1')
            && ! str_contains($request->header('Cookie')[0] ?? '', 'prihlasen-1'));
        $this->assertEqualsCanonicalizing(['PHPSESSID', 'fingerprint'], array_column($response->json('cookies'), 'name'));

        // Agent cookies jinde nevidí, ani v seznamu přístupů.
        $this->getJson('/nastroje/api/agent/trezor', $this->agent())->assertOk()->assertDontSee('zarizeni-1');
        $this->assertArrayNotHasKey('login_cookies', $entry->toArray());

        // Jiný účet: uložená relace i zařízení patří starému.
        $entry->update(['username' => 'jiny@taveo.cz']);
        $this->assertNull($entry->refresh()->login_cookies);
    }

    public function test_vyzkouset_prihlaseni_vzdy_overi_heslo(): void
    {
        $form = '<form action="https://www.eshop.cz/admin/login/" method="post"><input type="email" name="email"><input type="password" name="password"></form>';

        Http::fake([
            'https://www.eshop.cz/admin/' => Http::response('<h1>Přehled</h1>'),
            'https://www.eshop.cz/admin/login/' => Http::sequence()
                ->push($form)
                ->push('<h1>Přehled</h1>', 200, ['Set-Cookie' => 'PHPSESSID=nova; path=/; domain=www.eshop.cz']),
        ]);

        $entry = VaultEntry::query()->create([
            'type' => VaultType::Shoptet,
            'name' => 'Shoptet',
            'url' => 'https://www.eshop.cz',
            'username' => 'agent@taveo.cz',
            'secret' => 'shoptet-heslo',
            'agent_access' => 'read',
            'login_cookies' => [['Name' => 'PHPSESSID', 'Value' => 'stara', 'Domain' => 'www.eshop.cz', 'Path' => '/']],
        ]);

        $cookies = app(LoginBroker::class)->login($entry, resume: false);

        $this->assertSame('nova', $cookies[0]['value']);
        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://www.eshop.cz/admin/');
    }

    public function test_prihlaseni_s_overenim_nejsem_robot_srozumitelne_odmitne(): void
    {
        Http::fake(['pizzadigomma.eu/*' => Http::response('<form method="post" action="https://pizzadigomma.eu/wp-login.php"><input name="log"><input type="password" name="pwd"><div class="cf-turnstile"></div></form>')]);

        $entry = VaultEntry::query()->create([
            'type' => VaultType::WordPress,
            'name' => 'WordPress',
            'url' => 'https://pizzadigomma.eu',
            'username' => 'taveo-agent',
            'secret' => 'wp-heslo',
            'agent_access' => 'read',
        ]);

        $this->postJson("/nastroje/api/agent/trezor/{$entry->id}/prihlasit", [], $this->agent())
            ->assertStatus(422)
            ->assertJsonPath('ok', false);

        $this->assertStringContainsString('aplikační heslo', $entry->logs()->value('detail'));
        Http::assertSentCount(1);
    }

    public function test_skript_ochrany_mimo_formular_prihlaseni_nezastavi(): void
    {
        // Výjimka pro IP serveru: pole Turnstile ve formuláři chybí, skript pluginu na stránce zůstal.
        $login = '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js"></script>'
            .'<form method="post" action="https://pizzadigomma.eu/wp-login.php"><input name="log"><input type="password" name="pwd"></form>';

        Http::fake([
            'pizzadigomma.eu/*' => Http::sequence()
                ->push($login)
                ->push('<h1>Nástěnka</h1>', 200, ['Set-Cookie' => 'wordpress_logged_in=ano; path=/; domain=pizzadigomma.eu']),
        ]);

        $entry = VaultEntry::query()->create([
            'type' => VaultType::WordPress,
            'name' => 'WordPress',
            'url' => 'https://pizzadigomma.eu',
            'username' => 'taveo-agent',
            'secret' => 'wp-heslo',
            'agent_access' => 'read',
        ]);

        $this->postJson("/nastroje/api/agent/trezor/{$entry->id}/prihlasit", [], $this->agent())
            ->assertOk()
            ->assertJsonPath('cookies.0.value', 'ano');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request['log'] === 'taveo-agent');
    }

    public function test_neuspesne_prihlaseni_se_skriptem_ochrany_ji_zmini(): void
    {
        $login = '<script src="https://www.google.com/recaptcha/api.js?render=klic"></script><script>grecaptcha.ready(() => {});</script>'
            .'<form method="post" action="https://pizzadigomma.eu/wp-login.php"><input name="log"><input type="password" name="pwd"></form>';

        Http::fake(['pizzadigomma.eu/*' => Http::response($login)]);

        $entry = VaultEntry::query()->create([
            'type' => VaultType::WordPress,
            'name' => 'WordPress',
            'url' => 'https://pizzadigomma.eu',
            'username' => 'taveo-agent',
            'secret' => 'wp-heslo',
            'agent_access' => 'read',
        ]);

        $this->postJson("/nastroje/api/agent/trezor/{$entry->id}/prihlasit", [], $this->agent())
            ->assertStatus(422);

        $this->assertStringContainsString('nejsem robot', $entry->logs()->value('detail'));
        Http::assertSentCount(2);
    }

    public function test_cesty_nevedou_ven_ze_slozky_webu(): void
    {
        $this->assertSame('/www/wp-content/a.php', VaultPath::join('/www', '/wp-content//./a.php'));
        $this->assertSame('/www', VaultPath::join('/www/', ''));
        $this->expectExceptionMessage('ven ze složky');
        VaultPath::normalize('wp-content/../../etc');
    }

    public function test_sftp_pozna_podle_nastaveni_i_u_starsich_pristupu(): void
    {
        $entry = new VaultEntry(['type' => VaultType::Ftp, 'url' => 'pizzadigomma-www.zcom.cz', 'username' => 'sftp_pizza', 'settings' => ['port' => 1300, 'tls' => true]]);
        $this->assertSame('sftp', $entry->ftpProtocol());
        $this->assertSame('sftp', $entry->toAgent()['protokol']);

        $entry->settings = ['protocol' => 'ftp'];
        $this->assertSame('ftp', $entry->ftpProtocol());

        $this->assertSame('ftps', VaultEntry::guessFtpProtocol('ftp.pizzadigomma.eu', 'pizza', 21, true));
        $this->assertSame('ftp', VaultEntry::guessFtpProtocol('ftp.pizzadigomma.eu', 'pizza', 21, false));
        $this->assertSame('sftp', VaultEntry::guessFtpProtocol('sftp://ftp.pizzadigomma.eu', 'pizza', null, true));
        $this->assertSame('sftp', VaultEntry::guessFtpProtocol('ftp.pizzadigomma.eu', 'pizza', 22, true));
    }

    public function test_sftp_se_pripojuje_pres_ssh_a_chybu_rekne_srozumitelne(): void
    {
        $entry = new VaultEntry(['type' => VaultType::Ftp, 'url' => '127.0.0.1', 'username' => 'pizza', 'settings' => ['protocol' => 'sftp', 'port' => 1]]);
        $entry->secret = 'heslo';

        $this->expectExceptionMessage('Na SFTP server 127.0.0.1:1 se nepodařilo připojit');
        (new FtpConnector)->open($entry);
    }

    public function test_v_nastrojich_se_heslo_uklada_do_kolonek_a_zobrazi_az_po_zadani_hesla(): void
    {
        $tom = User::factory()->create(['role' => UserRole::Admin, 'name' => 'Tom', 'password' => 'heslo-toma-123']);
        $this->actingAs($tom);
        Filament::setCurrentPanel('tools');

        Livewire::test(CreateVaultEntry::class)
            ->fillForm([
                'type' => VaultType::Ftp->value,
                'client_id' => $this->client->getKey(),
                'name' => 'FTP webu',
                'url' => 'ftp.pizzadigomma.eu',
                'username' => 'pizza',
                'secret' => 'nove-ftp-heslo',
                'settings.protocol' => 'sftp',
                'settings.root' => '/www',
                'agent_access' => 'approve',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $entry = VaultEntry::query()->sole();
        $this->assertSame('nove-ftp-heslo', $entry->secret);
        $this->assertSame('Tom', $entry->created_by);
        $this->assertSame('sftp', $entry->ftpProtocol());
        $this->assertSame(22, (int) $entry->setting('port'));

        // Do formuláře úprav se heslo nenačte a prázdné pole ho nesmaže.
        Livewire::test(EditVaultEntry::class, ['record' => $entry->getKey()])
            ->assertFormSet(['secret' => null, 'username' => 'pizza'])
            ->fillForm(['name' => 'FTP na zcomu'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('nove-ftp-heslo', $entry->fresh()->secret);
        $this->assertSame('FTP na zcomu', $entry->fresh()->name);

        Livewire::test(ListVaultEntries::class)
            ->assertSee('FTP na zcomu')
            ->assertDontSee('nove-ftp-heslo')
            ->callTableAction('reveal', $entry, ['password' => 'spatne'])
            ->assertHasTableActionErrors(['password']);

        $this->assertFalse(VaultLock::isUnlocked());

        Livewire::test(ListVaultEntries::class)
            ->callTableAction('reveal', $entry, ['password' => 'heslo-toma-123'])
            ->assertHasNoTableActionErrors();

        $this->assertTrue(VaultLock::isUnlocked());
        $this->assertSame(['created', 'updated', 'unlocked', 'revealed'], $entry->logs()->orderBy('id')->pluck('action')->all());

        Livewire::test(ListVaultEntries::class)
            ->mountTableAction('reveal', $entry)
            ->assertMountedActionModalSee('nove-ftp-heslo');

        $this->assertSame('revealed', $entry->logs()->latest('id')->value('action'));
    }
}

/** FTP v paměti: cesta => obsah, složky se odvodí z cest. */
class InMemoryFtp implements FtpClient
{
    public function __construct(public array $files = []) {}

    public function list(string $path): array
    {
        $prefix = $path === '' ? '' : rtrim($path, '/').'/';
        $items = [];

        foreach ($this->files as $file => $contents) {
            if (! str_starts_with($file, $prefix)) {
                continue;
            }

            $rest = explode('/', substr($file, strlen($prefix)));
            $items[$rest[0]] = count($rest) > 1
                ? ['name' => $rest[0], 'type' => 'dir', 'size' => null, 'modified' => null]
                : ['name' => $rest[0], 'type' => 'file', 'size' => strlen($contents), 'modified' => null];
        }

        ksort($items);

        return array_values($items);
    }

    public function get(string $path): ?string
    {
        return $this->files[$path] ?? null;
    }

    public function put(string $path, string $contents): void
    {
        $this->files[$path] = $contents;
    }

    public function delete(string $path): void
    {
        unset($this->files[$path]);
    }

    public function close(): void {}
}
