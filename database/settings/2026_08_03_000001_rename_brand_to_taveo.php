<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Přejmenování značky TAVO → Taveo a přesun na doménu taveo.cz.
 *
 * Hodnoty se mění migrací, ne editací starších migrací — díky tomu dojde
 * ke stejnému výsledku na běžící instalaci i na čerstvě nasazeném serveru,
 * kde nejdřív proběhnou původní migrace s původním názvem.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->set([
            'site.brand_name' => 'Taveo',
            'site.copyright' => '© '.date('Y').' Taveo',
            'site.footer_columns' => [
                [
                    'title' => 'Služby',
                    'links' => [
                        ['label' => 'Tvorba webových stránek', 'url' => '/sluzby/tvorba-webovych-stranek'],
                        ['label' => 'Tvorba e-shopu', 'url' => '/sluzby/tvorba-eshopu'],
                        ['label' => 'Reklama a marketing', 'url' => '/sluzby/reklama-a-marketing'],
                        ['label' => 'Správa a rozvoj webu', 'url' => '/sluzby/sprava-a-rozvoj-webu'],
                    ],
                ],
                [
                    'title' => 'Taveo',
                    'links' => [
                        ['label' => 'Reference', 'url' => '/reference'],
                        ['label' => 'Kdo jsme', 'url' => '/#lide'],
                        ['label' => 'Jak pracujeme', 'url' => '/#proces'],
                    ],
                ],
            ],

            'contact.email' => 'ahoj@taveo.cz',
            'contact.company_name' => 'Taveo',
            'contact.lead_recipients' => ['ahoj@taveo.cz'],

            'home.founders_intro' => 'Pavel osm let řeší reklamu a sociální sítě pro e-shopy i firmy ve službách, Tom staví weby a e-shopy pro menší firmy z Hradce a okolí. Dlouho jsme si posílali práci navzájem, tak jsme z toho udělali Taveo.',

            'seo.title_suffix' => ' | Taveo',
        ]);
    }

    /**
     * SettingsMigration sám `set()` nemá — nabízí jen `$this->migrator`.
     * Tohle drží čitelný zápis „klíč => hodnota" a zároveň zvládne
     * i klíč, který ještě neexistuje (na čerstvé instalaci).
     *
     * @param  array<string, mixed>  $values
     */
    private function set(array $values): void
    {
        foreach ($values as $property => $value) {
            $this->migrator->exists($property)
                ? $this->migrator->update($property, fn () => $value)
                : $this->migrator->add($property, $value);
        }
    }
};
