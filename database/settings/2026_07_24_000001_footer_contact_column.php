<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Patička odkazovala ve sloupci „Napište si rovnou“ na osobní weby zakladatelů
 * (pavelvcelis.cz, juliatom.cz). Návštěvník tak nevěděl, kam se má vlastně
 * ozvat. Sloupec se ruší — kontakt teď patička bere přímo z ContactSettings,
 * aby byl e-mail a telefon na webu jen na jednom místě.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->set(['site.footer_columns' => [
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
                'title' => 'TAVO',
                'links' => [
                    ['label' => 'Reference', 'url' => '/reference'],
                    ['label' => 'Kdo jsme', 'url' => '/#lide'],
                    ['label' => 'Jak pracujeme', 'url' => '/#proces'],
                ],
            ],
        ]]);
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
