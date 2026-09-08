<?php

namespace App\Http\Requests;

use App\Rules\Turnstile;
use Illuminate\Foundation\Http\FormRequest;

class LeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'topic' => ['nullable', 'string', 'max:120'],
            'budget' => ['nullable', 'string', 'max:120'],
            'message' => ['required', 'string', 'min:10', 'max:4000'],
            'gdpr' => ['accepted'],
            // Honeypot — skryté pole, které smí vyplnit jen robot. Turnstile ho
            // nenahrazuje, jen doplňuje: nestojí nic a odfiltruje spam dřív,
            // než se kvůli němu volá Cloudflare.
            'website' => ['prohibited'],
            // Turnstile se ověřuje, jen když jsou v `.env` klíče od Cloudflare.
            'cf-turnstile-response' => Turnstile::enabled()
                ? ['required', new Turnstile($this->ip())]
                : [],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'jméno',
            'company' => 'firma',
            'email' => 'e-mail',
            'phone' => 'telefon',
            'topic' => 'o co jde',
            'budget' => 'rozpočet',
            'message' => 'zpráva',
            'gdpr' => 'souhlas se zpracováním údajů',
            'cf-turnstile-response' => 'ověření proti robotům',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'gdpr.accepted' => 'Bez souhlasu se zpracováním údajů vám bohužel nemůžeme odpovědět.',
            'website.prohibited' => 'Formulář se nepodařilo odeslat.',
            'message.min' => 'Napište nám prosím alespoň pár vět, ať víme, o co jde.',
            'cf-turnstile-response.required' => 'Ověření, že nejste robot, neproběhlo. Načtěte prosím stránku znovu.',
        ];
    }
}
