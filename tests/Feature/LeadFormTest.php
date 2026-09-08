<?php

namespace Tests\Feature;

use App\Mail\LeadReceived;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LeadFormTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    private function validData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jan Novák',
            'company' => 'Novák s.r.o.',
            'email' => 'jan@novak.cz',
            'phone' => '+420 777 123 456',
            'topic' => 'Nový web',
            'budget' => '100–300 tis. Kč',
            'message' => 'Potřebujeme nový web pro naši firmu, máme zastaralou prezentaci.',
            'gdpr' => '1',
        ], $overrides);
    }

    public function test_upozorneni_ma_kazdy_udaj_na_svem_radku(): void
    {
        $data = $this->validData(['message' => 'Chceme e-shop na Shoptetu.']);
        unset($data['gdpr']);

        $lead = Lead::create($data + ['status' => 'new']);

        $html = (new LeadReceived($lead))->render();

        // Markdown slepí sousední řádky do jednoho odstavce. Seznam to drží
        // oddělené, což je přesně to, co v e-mailu chybělo.
        foreach (['Jméno', 'Firma', 'E-mail', 'Telefon', 'O co jde', 'Rozpočet'] as $label) {
            $this->assertStringContainsString($label, $html);
        }

        $this->assertGreaterThanOrEqual(6, substr_count($html, '<li'));
        $this->assertStringContainsString('mailto:jan@novak.cz', $html);
        $this->assertStringContainsString('Chceme e-shop na Shoptetu.', $html);
    }

    public function test_bez_klicu_od_cloudflare_se_turnstile_nepta(): void
    {
        Mail::fake();
        config(['services.turnstile.secret' => null]);

        // Bez tokenu a přesto projde — jinak by vypnutá ochrana zablokovala web.
        $this->postJson('/poptavka', $this->validData())->assertOk();

        $this->assertSame(1, Lead::count());
    }

    public function test_zapnuty_turnstile_odmitne_odeslani_bez_tokenu(): void
    {
        Mail::fake();
        Http::fake();
        config(['services.turnstile.secret' => 'tajny-klic-jen-pro-test']);

        $this->postJson('/poptavka', $this->validData())
            ->assertStatus(422)
            ->assertJsonValidationErrors('cf-turnstile-response');

        $this->assertSame(0, Lead::count());
        // Chybějící token se pozná z pravidla `required`, Cloudflare se neptáme.
        Http::assertNothingSent();
    }

    public function test_zapnuty_turnstile_pusti_overene_odeslani(): void
    {
        Mail::fake();
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);
        config(['services.turnstile.secret' => 'tajny-klic-jen-pro-test']);

        $this->postJson('/poptavka', $this->validData(['cf-turnstile-response' => 'token-z-widgetu']))
            ->assertOk();

        $this->assertSame(1, Lead::count());
    }

    public function test_odeslani_na_pozadi_vrati_json_misto_presmerovani(): void
    {
        Mail::fake();

        $this->postJson('/poptavka', $this->validData())
            ->assertOk()
            ->assertExactJson(['ok' => true]);

        $this->assertSame('Jan Novák', Lead::sole()->name);
    }

    public function test_chyby_pri_odeslani_na_pozadi_prijdou_po_polich(): void
    {
        $this->postJson('/poptavka', $this->validData(['email' => 'tohle není e-mail']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertSame(0, Lead::count());
    }

    public function test_odeslani_ulozi_poptavku_a_posle_e_mail(): void
    {
        Mail::fake();

        $this->post('/poptavka', $this->validData())
            ->assertRedirect()
            ->assertSessionHas('lead_sent', true);

        $lead = Lead::sole();
        $this->assertSame('Jan Novák', $lead->name);
        $this->assertSame('new', $lead->status);

        Mail::assertSent(LeadReceived::class);
    }

    public function test_bez_souhlasu_gdpr_neprojde(): void
    {
        $this->post('/poptavka', $this->validData(['gdpr' => null]))
            ->assertSessionHasErrors('gdpr');

        $this->assertSame(0, Lead::count());
    }

    public function test_prilis_kratka_zprava_neprojde(): void
    {
        $this->post('/poptavka', $this->validData(['message' => 'ahoj']))
            ->assertSessionHasErrors('message');
    }

    public function test_neplatny_e_mail_neprojde(): void
    {
        $this->post('/poptavka', $this->validData(['email' => 'neni-email']))
            ->assertSessionHasErrors('email');
    }

    public function test_vyplneny_honeypot_znamena_robota(): void
    {
        $this->post('/poptavka', $this->validData(['website' => 'https://spam.example']))
            ->assertSessionHasErrors('website');

        $this->assertSame(0, Lead::count());
    }
}
