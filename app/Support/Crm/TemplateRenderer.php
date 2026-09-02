<?php

namespace App\Support\Crm;

use App\Models\Crm\Company;
use App\Models\Crm\Contact;
use App\Models\Crm\MessageTemplate;

/**
 * Dosazení údajů firmy do šablony zprávy.
 *
 * Placeholdery jsou česky a bez logiky — šablony píšeme my dva a mají zůstat
 * čitelné jako běžný text, ne jako kód.
 */
class TemplateRenderer
{
    /** Co všechno umíme dosadit. Ukazuje se u editace šablony jako nápověda. */
    public const PLACEHOLDERS = [
        '{{firma}}' => 'název firmy',
        '{{jmeno}}' => 'jméno kontaktní osoby',
        '{{bolest}}' => 'pozorovaná bolest z karty firmy',
        '{{reference}}' => 'reference, kterou argumentujeme',
        '{{web}}' => 'web firmy',
        '{{mesto}}' => 'město',
    ];

    /**
     * Vyplněná šablona pro konkrétní firmu.
     *
     * @return array{subject: ?string, body: string}
     */
    public static function render(MessageTemplate $template, Company $company, ?Contact $contact = null): array
    {
        $contact ??= $company->contacts()->where('is_primary', true)->first()
            ?? $company->contacts()->first();

        $values = [
            '{{firma}}' => $company->name,
            // Oslovení musí fungovat i bez známého jména, jinak by z e-mailu
            // koukalo prázdné místo. „Dobrý den," je pořád lepší než „Dobrý den ,".
            '{{jmeno}}' => trim((string) $contact?->name),
            '{{bolest}}' => trim((string) $company->pain),
            '{{reference}}' => trim((string) $company->reference_to_use),
            '{{web}}' => trim((string) ($company->domain ?: $company->website)),
            '{{mesto}}' => trim((string) $company->city),
        ];

        return [
            'subject' => $template->subject ? self::replace($template->subject, $values) : null,
            'body' => self::replace($template->body, $values),
        ];
    }

    /**
     * Dosazení včetně úklidu po prázdných hodnotách. Bez něj by v textu
     * zůstaly osiřelé čárky a dvojité mezery — a to je na studeném e-mailu
     * to první, čeho si příjemce všimne.
     *
     * @param  array<string, string>  $values
     */
    private static function replace(string $text, array $values): string
    {
        $text = strtr($text, $values);

        $text = preg_replace('~[ \t]+~', ' ', $text);           // dvojité mezery
        $text = preg_replace('~ ([,.!?])~', '$1', $text);       // mezera před interpunkcí
        $text = preg_replace('~,\s*,~', ',', $text);            // dvě čárky za sebou
        $text = preg_replace('~,\s*([.!?])~', '$1', $text);     // čárka těsně před tečkou

        // Dosazená bolest nebo reference bývá celá věta včetně tečky, takže se
        // sejde s tečkou ze šablony. „…objednávek.." je první, čeho si příjemce
        // studeného e-mailu všimne.
        $text = preg_replace('~([.!?])[.]+~', '$1', $text);

        return trim($text);
    }
}
