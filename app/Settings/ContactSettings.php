<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Kontaktní údaje — používají se v navigaci, patičce, CTA sekcích i ve formuláři.
 * Nikde jinde je needuplikuj; vždy čti odsud.
 * Edituje se ve Filamentu: Nastavení → Kontakt.
 */
class ContactSettings extends Settings
{
    public string $email;

    public string $phone;

    public ?string $company_name;

    public ?string $ico;

    public ?string $dic;

    public ?string $address;

    public array $socials;

    public array $lead_recipients;

    /** Telefonní číslo ve formátu pro href="tel:" */
    public function phoneHref(): string
    {
        return 'tel:'.preg_replace('/[^0-9+]/', '', $this->phone);
    }

    public function emailHref(): string
    {
        return 'mailto:'.$this->email;
    }

    /** @return array<int, string> */
    public function recipientEmails(): array
    {
        return collect($this->lead_recipients)
            ->filter(fn ($email) => is_string($email) && $email !== '')
            ->values()
            ->all();
    }

    public static function group(): string
    {
        return 'contact';
    }
}
