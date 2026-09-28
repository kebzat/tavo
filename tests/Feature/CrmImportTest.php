<?php

namespace Tests\Feature;

use App\Enums\Crm\CompanySegment;
use App\Enums\Crm\Priority;
use App\Models\Crm\Company;
use App\Support\Crm\CompanyCsvImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'segment,firma,mesto,obor,web,platforma,bolest,balicek,reference,kontakt,priorita';

    /** Zapíše CSV do dočasného souboru a vrátí cestu. */
    private function csv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'crm').'.csv';
        file_put_contents($path, $contents);

        return $path;
    }

    /*
    |--------------------------------------------------------------------------
    | Import firem z CSV
    |--------------------------------------------------------------------------
    */

    public function test_import_zalozi_firmy_i_kontakty(): void
    {
        $importer = new CompanyCsvImporter;
        $path = $this->csv(self::HEADER."\n"
            .'Lokální firma,Pekárna U Nádraží,Hradec Králové,gastro,https://www.pekarna.cz,WordPress,"Na mobilu nejde přečíst otevírací doba",Nový web,Včely Uhersko,"info@pekarna.cz, +420 605 112 334 (Jana Krupičková)",A'."\n"
            .'E-shop,Sportovní výživa,Pardubice,e-commerce,sportvyziva.cz,Shoptet,"Dopravce se doplňuje ručně",Napojení,Hop n Joy,objednavky@sportvyziva.cz,B'."\n");

        $parsed = $importer->read($path);
        $result = $importer->import($parsed['rows'], $importer->guessMapping($parsed['header']));

        $this->assertSame(2, $result['created']);
        $this->assertSame(0, $result['skipped']);

        $pekarna = Company::where('domain', 'pekarna.cz')->firstOrFail();

        $this->assertSame('Pekárna U Nádraží', $pekarna->name);
        $this->assertSame('Hradec Králové', $pekarna->city);
        $this->assertSame('WordPress', $pekarna->platform);
        $this->assertSame(CompanySegment::Local, $pekarna->segment);
        $this->assertSame(Priority::A, $pekarna->priority);

        $contact = $pekarna->contacts()->firstOrFail();

        $this->assertSame('Jana Krupičková', $contact->name);
        $this->assertSame('info@pekarna.cz', $contact->email);
        $this->assertSame('+420 605 112 334', $contact->phone);
        $this->assertTrue($contact->is_primary);
    }

    public function test_import_preskoci_duplicity_podle_domeny(): void
    {
        Company::create([
            'name' => 'Pekárna',
            'website' => 'pekarna.cz',
            'segment' => CompanySegment::Local,
            'source' => 'research',
        ]);

        $importer = new CompanyCsvImporter;
        $path = $this->csv(self::HEADER."\n"
            .'Lokální firma,Pekárna U Nádraží,Hradec Králové,gastro,https://www.pekarna.cz,,,,,,'."\n"
            .'Lokální firma,Nová firma,Jičín,truhlářství,truhlarstvi.cz,,,,,,'."\n"
            // Tatáž doména dvakrát v jednom souboru se nesmí založit dvakrát.
            .'Lokální firma,Truhlářství podruhé,Jičín,truhlářství,https://truhlarstvi.cz/kontakt,,,,,,'."\n");

        $parsed = $importer->read($path);
        $result = $importer->import($parsed['rows'], $importer->guessMapping($parsed['header']));

        $this->assertSame(1, $result['created']);
        $this->assertSame(2, $result['skipped']);
        $this->assertCount(2, $result['duplicates']);
        $this->assertSame(2, Company::count());
    }

    public function test_import_prelozi_segmenty_z_ceskych_nazvu(): void
    {
        $importer = new CompanyCsvImporter;
        $path = $this->csv(self::HEADER."\n"
            .'Zubní / zdraví,Ordinace,Pardubice,,ordinace.cz,,,,,,'."\n"
            .'SVJ / správa,SVJ 1204,Hradec,,,,,,,,'."\n"
            .'Něco úplně jiného,Divná firma,Praha,,divna.cz,,,,,,'."\n");

        $parsed = $importer->read($path);
        $importer->import($parsed['rows'], $importer->guessMapping($parsed['header']));

        $this->assertSame(CompanySegment::DentalHealth, Company::where('name', 'Ordinace')->first()->segment);
        $this->assertSame(CompanySegment::Svj, Company::where('name', 'SVJ 1204')->first()->segment);
        $this->assertSame(CompanySegment::Other, Company::where('name', 'Divná firma')->first()->segment);
    }

    public function test_radek_bez_nazvu_firmy_se_nenaimportuje(): void
    {
        $importer = new CompanyCsvImporter;
        $path = $this->csv(self::HEADER."\n".'Lokální firma,,Hradec,,neco.cz,,,,,,'."\n");

        $parsed = $importer->read($path);
        $result = $importer->import($parsed['rows'], $importer->guessMapping($parsed['header']));

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['invalid']);
    }

    /**
     * Rešerše často kontakt nenajde a napíše „nešlo ověřit". Prázdná kontaktní
     * karta by byla balast, ale informaci samotnou ztratit nechceme.
     */
    public function test_text_bez_kontaktu_skonci_v_poznamce_firmy(): void
    {
        $importer = new CompanyCsvImporter;
        $path = $this->csv(self::HEADER."\n"
            .'Lokální firma,Firma bez kontaktu,Hradec,,bezkontaktu.cz,,,,,nešlo ověřit,'."\n"
            .'Lokální firma,Firma s formulářem,Hradec,,sformularem.cz,,,,,přes /Kontakty,'."\n");

        $parsed = $importer->read($path);
        $importer->import($parsed['rows'], $importer->guessMapping($parsed['header']));

        $bez = Company::where('name', 'Firma bez kontaktu')->firstOrFail();

        $this->assertSame(0, $bez->contacts()->count());
        $this->assertStringContainsString('nešlo ověřit', $bez->notes);

        $this->assertSame(0, Company::where('name', 'Firma s formulářem')->first()->contacts()->count());
    }

    public function test_kontakt_bez_jmena_dostane_poznamku_z_reserse(): void
    {
        $parsed = (new CompanyCsvImporter)->parseContact('info@firma.cz, 777 123 456');

        $this->assertNull($parsed['name']);
        $this->assertSame('info@firma.cz', $parsed['email']);
        $this->assertSame('777 123 456', $parsed['phone']);
        $this->assertStringContainsString('(kontakt z rešerše)', $parsed['notes']);
    }

    public function test_soubor_se_strednikem_a_bom_se_precte(): void
    {
        $importer = new CompanyCsvImporter;
        $path = $this->csv("\xEF\xBB\xBF".'segment;firma;mesto;web'."\n".'Lokální firma;Firma s BOM;Hradec;firmabom.cz'."\n");

        $parsed = $importer->read($path);

        $this->assertSame('segment', $parsed['header'][0]);

        $mapping = $importer->guessMapping($parsed['header']);
        $importer->import($parsed['rows'], $mapping);

        $this->assertSame(1, Company::where('name', 'Firma s BOM')->count());
    }

    private function token(): string
    {
        config()->set('crm.import_token', 'testovaci-token');

        return 'testovaci-token';
    }

    public function test_bez_nastaveneho_tokenu_endpointy_neexistuji(): void
    {
        config()->set('crm.import_token', null);

        $this->postJson('/nastroje/api/companies/import', ['companies' => [['website' => 'a.cz']]])->assertNotFound();
        $this->getJson('/nastroje/api/export/pipeline?token=cokoliv')->assertNotFound();
    }

    /** Zástupné znaky z ručně psané rešerše nejsou hodnoty. */
    public function test_pomlcka_a_otaznik_v_platforme_se_neulozi(): void
    {
        $importer = new CompanyCsvImporter;
        $path = $this->csv(self::HEADER."\n"
            .'Lokální firma,Firma A,Hradec,,firmaa.cz,?,,,,,'."\n"
            .'Lokální firma,Firma B,Hradec,,firmab.cz,-,,,,,'."\n");

        $parsed = $importer->read($path);
        $importer->import($parsed['rows'], $importer->guessMapping($parsed['header']));

        $this->assertNull(Company::where('name', 'Firma A')->first()->platform);
        $this->assertNull(Company::where('name', 'Firma B')->first()->platform);
    }

    public function test_export_pipeline_vraci_firmy_obchody_i_aktivity(): void
    {
        $token = $this->token();

        $company = Company::create([
            'name' => 'Pekárna',
            'website' => 'pekarna.cz',
            'segment' => CompanySegment::Local,
            'source' => 'research',
        ]);
        $company->deals()->create(['title' => 'Nový web', 'package' => 'new_website', 'value_czk' => 100000]);
        $company->activities()->create(['type' => 'email', 'subject' => 'Oslovení', 'happened_at' => now()]);

        // Aktivita starší než 30 dní do exportu nepatří.
        $company->activities()->create(['type' => 'email', 'subject' => 'Prehistorie', 'happened_at' => now()->subDays(45)]);

        $response = $this->getJson('/nastroje/api/export/pipeline?token='.$token)->assertOk();

        $this->assertCount(1, $response->json('companies'));
        $this->assertCount(1, $response->json('deals'));
        $this->assertCount(1, $response->json('activities'));
        $this->assertSame('pekarna.cz', $response->json('companies.0.domain'));
        $this->assertSame(5000, $response->json('deals.0.weighted_value_czk'));
    }
}
