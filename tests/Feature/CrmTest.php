<?php

namespace Tests\Feature;

use App\Enums\Crm\ActivityOutcome;
use App\Enums\Crm\ActivityType;
use App\Enums\Crm\CompanySegment;
use App\Enums\Crm\CompanySource;
use App\Enums\Crm\CompanyStatus;
use App\Enums\Crm\DealStage;
use App\Enums\Crm\TemplateChannel;
use App\Enums\UserRole;
use App\Filament\Tools\Actions\LogActivityAction;
use App\Filament\Tools\Resources\Companies\Pages\CreateCompany;
use App\Models\Crm\Activity;
use App\Models\Crm\Company;
use App\Models\Crm\Deal;
use App\Models\Crm\Demand;
use App\Models\Crm\MessageTemplate;
use App\Models\User;
use App\Support\Crm\Domain;
use App\Support\Crm\WeeklyKpi;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class CrmTest extends TestCase
{
    use RefreshDatabase;

    /**
     * CRM žije v panelu `tools`, ale výchozí je `admin`. Bez přepnutí by
     * Livewire hledal routy v tom špatném.
     */
    private function obchodnik(): User
    {
        Filament::setCurrentPanel('tools');

        return User::factory()->create(['role' => UserRole::Admin]);
    }

    private function firma(array $attributes = []): Company
    {
        return Company::create($attributes + [
            'name' => 'Zkušební firma',
            'segment' => CompanySegment::Local,
            'source' => CompanySource::Research,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Firmy a duplicity
    |--------------------------------------------------------------------------
    */

    public function test_domena_se_normalizuje_bez_protokolu_a_www(): void
    {
        $this->assertSame('firma.cz', Domain::normalize('https://www.firma.cz/kontakt'));
        $this->assertSame('firma.cz', Domain::normalize('FIRMA.cz'));
        $this->assertSame('firma.cz', Domain::normalize('http://firma.cz'));
        $this->assertNull(Domain::normalize(''));
        $this->assertNull(Domain::normalize('localhost'));
    }

    public function test_firma_si_doplni_domenu_pri_ulozeni(): void
    {
        $company = $this->firma(['website' => 'https://www.pekarna.cz/o-nas']);

        $this->assertSame('pekarna.cz', $company->domain);
    }

    public function test_duplicitni_domena_se_najde_bez_ohledu_na_tvar_adresy(): void
    {
        $existing = $this->firma(['name' => 'Pekárna', 'website' => 'pekarna.cz']);

        $duplicate = Company::withDomain('https://www.pekarna.cz/');

        $this->assertNotNull($duplicate);
        $this->assertSame($existing->getKey(), $duplicate->getKey());

        // Vlastní záznam se sám sobě duplicitou být nesmí, jinak by hlásil
        // konflikt pokaždé, když se karta jen uloží znovu.
        $this->assertNull(Company::withDomain('pekarna.cz', $existing->getKey()));
    }

    public function test_zalozeni_firmy_s_duplicitni_domenou_projde_a_jen_varuje(): void
    {
        $this->actingAs($this->obchodnik());
        $this->firma(['name' => 'Pekárna', 'website' => 'pekarna.cz']);

        Livewire::test(CreateCompany::class)
            ->fillForm([
                'name' => 'Pekárna, druhá pobočka',
                'website' => 'https://www.pekarna.cz',
                'segment' => CompanySegment::Local->value,
                'source' => CompanySource::Research->value,
                'status' => CompanyStatus::New->value,
                'priority' => 'B',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(2, Company::where('domain', 'pekarna.cz')->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Aktivity a termín dalšího kroku
    |--------------------------------------------------------------------------
    */

    public function test_zalogovani_aktivity_nastavi_termin_a_posune_stav(): void
    {
        $this->actingAs($this->obchodnik());
        $company = $this->firma();

        LogActivityAction::log($company, [
            'type' => ActivityType::Email,
            'subject' => 'První oslovení',
            'happened_at' => now(),
            'follow_up' => '3',
        ]);

        $company->refresh();

        $this->assertSame(CompanyStatus::Contacted, $company->status);
        $this->assertNotNull($company->next_action_at);
        $this->assertSame(now()->addDays(3)->toDateString(), $company->next_action_at->toDateString());
        $this->assertNotNull($company->last_activity_at);
    }

    public function test_poznamka_stav_firmy_neposouva(): void
    {
        $company = $this->firma();

        $company->activities()->create([
            'type' => ActivityType::Note,
            'subject' => 'Zjištění z rešerše',
            'happened_at' => now(),
        ]);

        $this->assertSame(CompanyStatus::New, $company->refresh()->status);
    }

    public function test_rozjednany_stav_uz_automat_neprepisuje(): void
    {
        $company = $this->firma(['status' => CompanyStatus::Proposal]);

        $company->activities()->create([
            'type' => ActivityType::Email,
            'subject' => 'Doplnění k nabídce',
            'happened_at' => now(),
        ]);

        $this->assertSame(CompanyStatus::Proposal, $company->refresh()->status);
    }

    /** Bez tohohle by přehled „Po termínu" nikdy nic neukázal. */
    public function test_propasnuty_follow_up_zustava_po_terminu(): void
    {
        $company = $this->firma();

        $company->activities()->create([
            'type' => ActivityType::Email,
            'subject' => 'Oslovení',
            'happened_at' => now()->subDays(10),
            'follow_up_at' => now()->subDays(3),
        ]);

        $company->refresh();

        $this->assertTrue($company->isOverdue());
        $this->assertSame(1, Company::overdue()->count());
    }

    /** Poznámka se nepočítá jako kontakt, termín tedy nesmí uklidit. */
    public function test_poznamka_nezrusi_cekajici_follow_up(): void
    {
        $company = $this->firma();
        $company->activities()->create([
            'type' => ActivityType::Email,
            'subject' => 'Oslovení',
            'happened_at' => now()->subDays(10),
            'follow_up_at' => now()->subDays(3),
        ]);

        $company->activities()->create([
            'type' => ActivityType::Note,
            'subject' => 'Našel jsem jejich ceník',
            'happened_at' => now(),
        ]);

        $this->assertTrue($company->refresh()->isOverdue());
    }

    public function test_dalsi_kontakt_bez_follow_upu_termin_uklidi(): void
    {
        $company = $this->firma();
        $company->activities()->create([
            'type' => ActivityType::Email,
            'subject' => 'Oslovení',
            'happened_at' => now()->subDays(10),
            'follow_up_at' => now()->subDays(3),
        ]);

        $company->activities()->create([
            'type' => ActivityType::Call,
            'subject' => 'Dovoláno, nemají zájem',
            'happened_at' => now(),
        ]);

        $this->assertNull($company->refresh()->next_action_at);
    }

    public function test_smazani_aktivity_termin_odstrani(): void
    {
        $company = $this->firma();
        $activity = $company->activities()->create([
            'type' => ActivityType::Email,
            'subject' => 'Oslovení',
            'happened_at' => now(),
            'follow_up_at' => now()->addDays(5),
        ]);

        $this->assertNotNull($company->refresh()->next_action_at);

        $activity->delete();

        $this->assertNull($company->refresh()->next_action_at);
    }

    public function test_odlozeni_posune_termin_ode_dneska(): void
    {
        $company = $this->firma();
        $company->activities()->create([
            'type' => ActivityType::Email,
            'subject' => 'Oslovení',
            'happened_at' => now()->subDays(10),
            'follow_up_at' => now()->subDays(3),
        ]);

        $company->refresh()->snooze(7);

        $this->assertSame(
            now()->addDays(7)->toDateString(),
            $company->refresh()->next_action_at->toDateString(),
        );
        // Odklad posouvá původní follow-up, nezakládá druhou aktivitu.
        $this->assertSame(1, $company->activities()->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Obchody
    |--------------------------------------------------------------------------
    */

    public function test_zmena_faze_srovna_pravdepodobnost_a_zapise_data(): void
    {
        $company = $this->firma();
        $deal = $company->deals()->create([
            'title' => 'Nový web',
            'package' => 'new_website',
            'stage' => DealStage::Lead,
            'value_czk' => 100000,
        ]);

        $this->assertSame(5, $deal->probability);

        $deal->update(['stage' => DealStage::ProposalSent]);
        $deal->refresh();

        $this->assertSame(50, $deal->probability);
        $this->assertNotNull($deal->proposal_sent_at);
        $this->assertSame(50000.0, $deal->weightedValue());

        $deal->update(['stage' => DealStage::Won]);
        $deal->refresh();

        $this->assertSame(100, $deal->probability);
        $this->assertNotNull($deal->won_at);
        // Odeslaná nabídka se nepřepisuje, jinak by o ni týdenní přehled přišel.
        $this->assertNotNull($deal->proposal_sent_at);
    }

    public function test_rucne_zadana_pravdepodobnost_ma_prednost_pred_fazi(): void
    {
        $deal = $this->firma()->deals()->create([
            'title' => 'Migrace',
            'package' => 'migration_shoptet',
            'stage' => DealStage::Lead,
        ]);

        $deal->update(['stage' => DealStage::Call, 'probability' => 90]);

        $this->assertSame(90, $deal->refresh()->probability);
    }

    /*
    |--------------------------------------------------------------------------
    | Týdenní čísla
    |--------------------------------------------------------------------------
    */

    public function test_tydenni_prehled_rozlisi_osloveni_od_follow_upu(): void
    {
        $monday = Carbon::now()->startOfWeek();
        $company = $this->firma();

        // První oslovení proběhlo dřív, tenhle týden je to už follow-up.
        $company->activities()->create([
            'type' => ActivityType::Email,
            'subject' => 'Oslovení',
            'happened_at' => $monday->copy()->subWeek(),
        ]);
        $company->activities()->create([
            'type' => ActivityType::Email,
            'subject' => 'Připomínka',
            'happened_at' => $monday->copy()->addDay(),
        ]);

        // Druhá firma je oslovená poprvé až tenhle týden.
        $fresh = $this->firma(['name' => 'Druhá firma']);
        $fresh->activities()->create([
            'type' => ActivityType::Linkedin,
            'subject' => 'Oslovení',
            'happened_at' => $monday->copy()->addDay(),
            'outcome' => ActivityOutcome::Positive,
        ]);

        $metrics = (new WeeklyKpi($monday))->metrics();

        $this->assertSame(1, $metrics['outreach']);
        $this->assertSame(1, $metrics['follow_ups']);
        $this->assertSame(1, $metrics['replies']);
    }

    public function test_tydenni_prehled_secte_nabidky_vyhrane_a_poptavky(): void
    {
        $monday = Carbon::now()->startOfWeek();
        $company = $this->firma();

        $company->deals()->create([
            'title' => 'Nabídka',
            'package' => 'new_website',
            'stage' => DealStage::Lead,
        ])->update(['stage' => DealStage::ProposalSent]);

        Deal::create([
            'company_id' => $company->getKey(),
            'title' => 'Vyhraný obchod',
            'package' => 'retainer',
            'value_czk' => 60000,
            'stage' => DealStage::Negotiation,
        ])->update(['stage' => DealStage::Won]);

        Demand::factory()->create(['replied_at' => $monday->copy()->addDay()]);

        $kpi = new WeeklyKpi($monday);
        $metrics = $kpi->metrics();

        $this->assertSame(1, $metrics['proposals']);
        $this->assertSame(1, $metrics['won']);
        $this->assertSame(60000, $kpi->wonValue());
        $this->assertSame(1, $metrics['demand_replies']);
    }

    public function test_hovor_bez_dovolani_se_do_cisel_nepocita(): void
    {
        $monday = Carbon::now()->startOfWeek();
        $company = $this->firma();

        $company->activities()->create([
            'type' => ActivityType::Call,
            'subject' => 'Nedovoláno',
            'happened_at' => $monday->copy()->addDay(),
            'outcome' => ActivityOutcome::NoAnswer,
        ]);
        $company->activities()->create([
            'type' => ActivityType::Call,
            'subject' => 'Dovoláno',
            'happened_at' => $monday->copy()->addDay(),
            'outcome' => ActivityOutcome::Neutral,
        ]);

        $this->assertSame(1, (new WeeklyKpi($monday))->metrics()['calls']);
    }

    /*
    |--------------------------------------------------------------------------
    | Přístup
    |--------------------------------------------------------------------------
    */

    public function test_crm_vyzaduje_prihlaseni(): void
    {
        $this->get('/nastroje/companies')->assertRedirect('/nastroje/login');
    }

    public function test_prehled_dnes_se_prihlasenemu_zobrazi(): void
    {
        $this->actingAs($this->obchodnik())
            ->get('/nastroje/today')
            ->assertOk()
            ->assertSee('Po termínu');
    }

    /**
     * Po importu rešerše čekají všechny firmy na první oslovení. Bez tohohle
     * bloku by přehled hlásil, že není co dělat, i když je práce na měsíc.
     */
    public function test_neoslovene_firmy_se_v_prehledu_dnes_nabizeji(): void
    {
        $this->firma(['name' => 'Čerstvý prospekt']);

        // Oslovená firma do fronty nepatří. V přehledu se objeví, ale jinde:
        // bez aktivity spadne do bloku „Bez pohybu", proto se počítá hlavička.
        $this->firma(['name' => 'Už oslovená', 'status' => CompanyStatus::Contacted]);

        $this->actingAs($this->obchodnik())
            ->get('/nastroje/today')
            ->assertOk()
            ->assertSee('K oslovení (1)')
            ->assertSee('Čerstvý prospekt');

        $this->assertSame(1, Company::query()->untouched()->count());
    }

    public function test_robots_zakazuje_indexaci_nastroju(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /nastroje');
    }

    /**
     * robots.txt je jen prosba a přihlašovací stránka je veřejná, takže
     * hlavička i meta musí platit i pro nepřihlášeného návštěvníka.
     */
    public function test_nastroje_maji_noindex_v_hlavicce_i_v_meta(): void
    {
        Filament::setCurrentPanel('tools');

        $this->get('/nastroje/login')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertSee('<meta name="robots" content="noindex, nofollow, noarchive">', escape: false);
    }

    /** Veřejný web se zákazem indexace nakazit nesmí. */
    public function test_verejny_web_zustava_indexovatelny(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeaderMissing('X-Robots-Tag');
    }

    /*
    |--------------------------------------------------------------------------
    | Šablony zpráv
    |--------------------------------------------------------------------------
    */

    /**
     * Šablony přiváží datová migrace, ne seeder. Seeder se pouští při zakládání
     * instance, takže na běžící web by se nedostaly.
     */
    public function test_sablony_zprav_prijedou_migraci(): void
    {
        $this->assertSame(6, MessageTemplate::count());
        $this->assertSame(6, MessageTemplate::active()->count());
        $this->assertTrue(MessageTemplate::where('channel', TemplateChannel::CallScript)->exists());
    }

    /** Opakované nasazení nesmí přepsat text, který si mezitím někdo upravil. */
    public function test_opakovana_migrace_sablony_neprepise(): void
    {
        $template = MessageTemplate::firstOrFail();
        $template->update(['body' => 'Vlastní znění.']);

        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        $this->assertSame(6, MessageTemplate::count());
        $this->assertSame('Vlastní znění.', $template->fresh()->body);
    }

    public function test_aktivita_pozna_prvni_osloveni_firmy(): void
    {
        $company = $this->firma();

        $first = $company->activities()->create([
            'type' => ActivityType::Email,
            'subject' => 'Oslovení',
            'happened_at' => now()->subDays(5),
        ]);
        $second = $company->activities()->create([
            'type' => ActivityType::Email,
            'subject' => 'Připomínka',
            'happened_at' => now(),
        ]);

        $this->assertTrue(Activity::find($first->getKey())->isFirstForCompany());
        $this->assertFalse(Activity::find($second->getKey())->isFirstForCompany());
    }
}
