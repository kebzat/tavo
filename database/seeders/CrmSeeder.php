<?php

namespace Database\Seeders;

use App\Enums\Crm\ActivityOutcome;
use App\Enums\Crm\ActivityType;
use App\Enums\Crm\CompanySegment;
use App\Enums\Crm\CompanySource;
use App\Enums\Crm\CompanyStatus;
use App\Enums\Crm\DealPackage;
use App\Enums\Crm\DealStage;
use App\Enums\Crm\DemandSource;
use App\Enums\Crm\Priority;
use App\Enums\UserRole;
use App\Models\Crm\Company;
use App\Models\Crm\Demand;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Naplnění CRM.
 *
 * Zakládá účty z .env a v local i dev navíc ukázková data, aby po instalaci
 * nebyla každá obrazovka prázdná. Na produkci se ukázková data neseedují —
 * za týden by z nich byla desítka záznamů, které nikdo nemaže, ale všichni
 * je obcházejí.
 *
 * Šablony zpráv tu nejsou schválně. Seeder se pouští při zakládání instance,
 * takže na běžící web by se nedostaly; přiváží je datová migrace
 * 2026_09_02_110000_seed_crm_message_templates.
 */
class CrmSeeder extends Seeder
{
    public function run(): void
    {
        $this->createUsers();

        if (app()->environment('local', 'development', 'testing')) {
            $this->createDemoData();
        }
    }

    /**
     * Účty z CRM_USERS ve tvaru „Jméno:email:heslo,Jméno:email:heslo".
     *
     * Existující účet se nechává být — přepsané heslo z .env by při každém
     * nasazení zrušilo změnu hesla udělanou v aplikaci.
     */
    private function createUsers(): void
    {
        $definition = trim((string) config('crm.users'));

        if ($definition === '') {
            $this->command?->warn('CRM_USERS není nastavené, účty se nezakládají. Použij `php artisan crm:user`.');

            return;
        }

        foreach (explode(',', $definition) as $entry) {
            $parts = array_map('trim', explode(':', trim($entry)));

            if (count($parts) < 2 || ! filter_var($parts[1], FILTER_VALIDATE_EMAIL)) {
                $this->command?->warn("Přeskakuji nečitelný záznam v CRM_USERS: {$entry}");

                continue;
            }

            [$name, $email] = $parts;

            if (User::where('email', $email)->exists()) {
                $this->command?->info("Účet {$email} už existuje, heslo ponecháno.");

                continue;
            }

            $password = $parts[2] ?? Str::password(16);

            User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                // Panel nástrojů je celý jen pro správce, viz User::canAccessPanel().
                'role' => UserRole::Admin,
                'email_verified_at' => now(),
            ]);

            $this->command?->info("Účet {$email} založen.");

            if (! isset($parts[2])) {
                $this->command?->warn("Heslo: {$password}");
            }
        }
    }

    /**
     * Ukázková data. Deset firem napříč segmenty, ke třem z nich kontakt,
     * obchod a kus historie, plus tři poptávky. Cílem je, aby po instalaci
     * nebyla každá obrazovka prázdná.
     */
    private function createDemoData(): void
    {
        if (Company::query()->exists()) {
            $this->command?->info('CRM už obsahuje firmy, ukázková data se přeskakují.');

            return;
        }

        $owner = User::query()->orderBy('id')->first();

        foreach ($this->demoCompanies() as $index => $data) {
            $company = Company::create($data['company'] + ['owner_id' => $owner?->getKey()]);

            if (isset($data['contact'])) {
                $company->contacts()->create($data['contact'] + ['is_primary' => true]);
            }

            foreach ($data['activities'] ?? [] as $activity) {
                $company->activities()->create($activity + ['user_id' => $owner?->getKey()]);
            }

            if (isset($data['deal'])) {
                $company->deals()->create($data['deal'] + ['owner_id' => $owner?->getKey()]);
            }
        }

        Demand::insert([
            [
                'source' => DemandSource::ShoptetPartners->value,
                'url' => 'https://partners.shoptet.cz/poptavky/2481',
                'title' => 'Migrace e-shopu s 1 200 produkty na Shoptet',
                'summary' => 'Stávající řešení na míru, potřeba přenést produkty, kategorie a objednávky. Napojení na Pohodu.',
                'posted_at' => now()->subDay()->toDateString(),
                'budget_estimate' => '80 000 až 120 000 Kč',
                'priority' => Priority::A->value,
                'status' => 'new',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'source' => DemandSource::Webtrh->value,
                'url' => 'https://www.webtrh.cz/poptavka/48120',
                'title' => 'Úpravy WooCommerce a zrychlení webu',
                'summary' => 'Web se načítá přes pět sekund, potřeba projít šablonu a pluginy.',
                'posted_at' => now()->subDays(2)->toDateString(),
                'budget_estimate' => 'do 30 000 Kč',
                'priority' => Priority::B->value,
                'status' => 'new',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'source' => DemandSource::NaVolneNoze->value,
                'url' => 'https://www.navolnenoze.cz/poptavky/9931',
                'title' => 'Nový web pro zubní ordinaci',
                'summary' => 'Pět podstránek, objednávkový formulář, texty dodá zadavatel.',
                'posted_at' => now()->subDays(4)->toDateString(),
                'budget_estimate' => 'neuvedeno',
                'priority' => Priority::C->value,
                'status' => 'new',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->command?->info('Založeno 10 ukázkových firem a 3 poptávky.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function demoCompanies(): array
    {
        return [
            [
                'company' => [
                    'name' => 'Pekárna U Nádraží',
                    'website' => 'pekarnaunadrazi.cz',
                    'city' => 'Hradec Králové',
                    'industry' => 'gastro',
                    'segment' => CompanySegment::Local,
                    'platform' => 'WordPress',
                    'pain' => 'Na mobilu se nedá přečíst otevírací doba, web je z roku 2016.',
                    'offer' => 'Nový web na WordPressu, focení provozovny, mapa a otevírací doba nahoře.',
                    'reference_to_use' => 'Včely Uhersko',
                    'priority' => Priority::A,
                    'source' => CompanySource::Research,
                    'status' => CompanyStatus::FollowUp,
                ],
                'contact' => ['name' => 'Jana Krupičková', 'role' => 'majitelka', 'email' => 'jana@pekarnaunadrazi.cz', 'phone' => '+420 605 112 334'],
                'activities' => [
                    [
                        'type' => ActivityType::Email,
                        'subject' => 'První oslovení',
                        'body' => 'Poslán studený e-mail s postřehem k otevírací době na mobilu.',
                        'happened_at' => now()->subDays(5),
                        'follow_up_at' => now()->subDay()->setTime(9, 0),
                    ],
                ],
            ],
            [
                'company' => [
                    'name' => 'Zubní ordinace Novotný',
                    'website' => 'zubninovotny.cz',
                    'city' => 'Pardubice',
                    'industry' => 'zdravotnictví',
                    'segment' => CompanySegment::DentalHealth,
                    'platform' => 'WordPress',
                    'pain' => 'Objednávka jen telefonem, sestra to zvedá mezi pacienty.',
                    'offer' => 'Objednávkový formulář a přehledný ceník.',
                    'reference_to_use' => 'ChrudimLab',
                    'priority' => Priority::A,
                    'source' => CompanySource::Research,
                    'status' => CompanyStatus::Replied,
                ],
                'contact' => ['name' => 'MUDr. Petr Novotný', 'role' => 'jednatel', 'email' => 'ordinace@zubninovotny.cz', 'phone' => '+420 466 123 456'],
                'activities' => [
                    [
                        'type' => ActivityType::Email,
                        'subject' => 'První oslovení',
                        'body' => 'Studený e-mail k objednávkám po telefonu.',
                        'happened_at' => now()->subDays(9),
                    ],
                    [
                        'type' => ActivityType::Email,
                        'subject' => 'Odpověď: zájem o hovor',
                        'body' => 'Odepsali, že objednávky řeší dlouhodobě. Chtějí zavolat příští týden.',
                        'happened_at' => now()->subDays(2),
                        'outcome' => ActivityOutcome::Positive,
                        'follow_up_at' => now()->addDays(2)->setTime(9, 0),
                    ],
                ],
                'deal' => [
                    'title' => 'Web s objednávkovým formulářem',
                    'package' => DealPackage::NewWebsite,
                    'value_czk' => 95000,
                    'stage' => DealStage::Replied,
                    'expected_close_at' => now()->addMonth()->toDateString(),
                ],
            ],
            [
                'company' => [
                    'name' => 'SVJ Slezské Předměstí 1204',
                    'city' => 'Hradec Králové',
                    'industry' => 'správa nemovitostí',
                    'segment' => CompanySegment::Svj,
                    'pain' => 'Dokumenty pro vlastníky rozesílá správce e-mailem, nikdo je nenajde.',
                    'offer' => 'Jednoduchý web s heslem pro vlastníky a archivem dokumentů.',
                    'priority' => Priority::C,
                    'source' => CompanySource::Referral,
                    'status' => CompanyStatus::New,
                ],
                'contact' => ['email' => 'predseda@svj1204.cz', 'notes' => '(kontakt z rešerše)'],
            ],
            [
                'company' => [
                    'name' => 'Konference Hradecké dny',
                    'website' => 'hradeckedny.cz',
                    'city' => 'Hradec Králové',
                    'industry' => 'konference',
                    'segment' => CompanySegment::Conference,
                    'platform' => 'Wix',
                    'pain' => 'Registrace přes Google formulář, platby ručně na faktury.',
                    'offer' => 'Registrace s platební bránou a automatickými fakturami.',
                    'priority' => Priority::B,
                    'source' => CompanySource::Research,
                    'status' => CompanyStatus::Contacted,
                ],
                'activities' => [
                    [
                        'type' => ActivityType::Linkedin,
                        'subject' => 'Oslovení přes LinkedIn',
                        'body' => 'Napsáno organizátorce, zatím bez odpovědi.',
                        'happened_at' => now()->subDays(12),
                    ],
                ],
            ],
            [
                'company' => [
                    'name' => 'Sportovní výživa Pardubice',
                    'website' => 'sportvyzivapce.cz',
                    'city' => 'Pardubice',
                    'industry' => 'e-commerce',
                    'segment' => CompanySegment::Eshop,
                    'platform' => 'Shoptet',
                    'pain' => 'Dopravce se do objednávky doplňuje ručně, denně kolem třiceti objednávek.',
                    'offer' => 'Napojení Zásilkovny a Pohody, měření nákupního procesu.',
                    'reference_to_use' => "Hop'n'Joy",
                    'priority' => Priority::A,
                    'source' => CompanySource::ShoptetDemands,
                    'status' => CompanyStatus::Proposal,
                ],
                'contact' => ['name' => 'Martin Dvořák', 'role' => 'provoz', 'email' => 'martin@sportvyzivapce.cz', 'phone' => '+420 777 908 112'],
                'activities' => [
                    [
                        'type' => ActivityType::DemandReply,
                        'subject' => 'Reakce na poptávku ze Shoptet Partners',
                        'happened_at' => now()->subDays(14),
                    ],
                    [
                        'type' => ActivityType::Call,
                        'subject' => 'Hovor k rozsahu napojení',
                        'body' => 'Prošli jsme, co se dnes dělá ručně. Chtějí nabídku do konce týdne.',
                        'happened_at' => now()->subDays(6),
                        'outcome' => ActivityOutcome::Positive,
                        'follow_up_at' => now()->addDays(3)->setTime(9, 0),
                    ],
                ],
                'deal' => [
                    'title' => 'Napojení Pohody a dopravců',
                    'package' => DealPackage::IntegrationPohodaCarrier,
                    'value_czk' => 140000,
                    'stage' => DealStage::ProposalSent,
                    'proposal_sent_at' => now()->subDays(2),
                    'expected_close_at' => now()->addWeeks(3)->toDateString(),
                ],
            ],
            [
                'company' => [
                    'name' => 'Studio Kolmá',
                    'website' => 'studiokolma.cz',
                    'city' => 'Praha',
                    'industry' => 'reklamní agentura',
                    'segment' => CompanySegment::Agency,
                    'pain' => 'Berou zakázky na Shoptet, ale nemají vlastního vývojáře.',
                    'offer' => 'Subdodávka vývoje, pevná cena na zadání.',
                    'priority' => Priority::B,
                    'source' => CompanySource::Linkedin,
                    'status' => CompanyStatus::Contacted,
                ],
                'contact' => ['name' => 'Eva Kolmá', 'role' => 'majitelka', 'email' => 'eva@studiokolma.cz'],
                'activities' => [
                    [
                        'type' => ActivityType::Email,
                        'subject' => 'Nabídka subdodávky',
                        'happened_at' => now()->subDays(3),
                        'follow_up_at' => now()->addDays(4)->setTime(9, 0),
                    ],
                ],
            ],
            [
                'company' => [
                    'name' => 'ChrudimLab',
                    'website' => 'chrudimlab.cz',
                    'city' => 'Chrudim',
                    'industry' => 'zubní laboratoř',
                    'segment' => CompanySegment::FormerClient,
                    'platform' => 'Laravel',
                    'pain' => 'Web běží dva roky beze změny, přibyly služby, které na něm nejsou.',
                    'offer' => 'Rozšíření webu a průběžná správa.',
                    'priority' => Priority::B,
                    'source' => CompanySource::Referral,
                    'status' => CompanyStatus::Won,
                ],
                'contact' => ['name' => 'Lukáš Marek', 'role' => 'jednatel', 'email' => 'marek@chrudimlab.cz'],
                'activities' => [
                    [
                        'type' => ActivityType::Meeting,
                        'subject' => 'Schůzka k rozvoji webu',
                        'happened_at' => now()->subDays(20),
                        'outcome' => ActivityOutcome::Positive,
                    ],
                ],
                'deal' => [
                    'title' => 'Průběžná správa webu',
                    'package' => DealPackage::Retainer,
                    'value_czk' => 60000,
                    'stage' => DealStage::Won,
                    'won_at' => now()->subDays(10),
                ],
            ],
            [
                'company' => [
                    'name' => 'Truhlářství Beneš',
                    'website' => 'truhlarstvibenes.cz',
                    'city' => 'Jičín',
                    'industry' => 'stavebnictví',
                    'segment' => CompanySegment::Local,
                    'platform' => 'Webnode',
                    'pain' => 'Fotky realizací jsou jen na Facebooku, web má jednu stránku.',
                    'offer' => 'Web s galerií realizací a poptávkovým formulářem.',
                    'priority' => Priority::C,
                    'source' => CompanySource::Research,
                    'status' => CompanyStatus::New,
                ],
            ],
            [
                'company' => [
                    'name' => 'Květinářství Náchod',
                    'website' => 'kvetinynachod.cz',
                    'city' => 'Náchod',
                    'industry' => 'maloobchod',
                    'segment' => CompanySegment::Eshop,
                    'platform' => 'Upgates',
                    'pain' => 'E-shop nemá měření, netuší, odkud chodí objednávky.',
                    'offer' => 'Audit měření, GA4 a napojení na reklamu.',
                    'priority' => Priority::B,
                    'source' => CompanySource::Upgates,
                    'status' => CompanyStatus::Lost,
                    'lost_reason' => 'Řeší to jejich stávající agentura.',
                ],
            ],
            [
                'company' => [
                    'name' => 'Autoservis Vlček',
                    'website' => 'autoservisvlcek.cz',
                    'city' => 'Hradec Králové',
                    'industry' => 'služby',
                    'segment' => CompanySegment::Local,
                    'pain' => 'Objednávkový formulář nefunguje, e-maily nikam nechodí.',
                    'offer' => 'Oprava formuláře a měření odeslaných poptávek.',
                    'priority' => Priority::A,
                    'source' => CompanySource::InboundForm,
                    'status' => CompanyStatus::Parked,
                    'notes' => 'Ozvou se sami na jaře, teď stěhují dílnu.',
                ],
                'contact' => ['name' => 'Roman Vlček', 'email' => 'servis@autoservisvlcek.cz', 'phone' => '+420 495 221 908'],
            ],
        ];
    }
}
