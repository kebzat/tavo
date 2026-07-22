<?php

namespace Tests\Feature;

use App\Mail\LeadReceived;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
