<?php

namespace App\Support;

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Základ pro settings migrace, které sahají na obsah webu.
 *
 * Obsah bydlí v databázi a správce ho edituje v administraci. Migrace je
 * zároveň jediná cesta, jak texty z repozitáře dostat na produkci, takže si
 * ty dvě věci můžou vjet do vlasů: nasazení, které přepíše hodnotu natvrdo,
 * smaže správci to, co si tam napsal.
 *
 * Proto tři metody s jasně odlišeným rizikem. Výchozí volba je `add()`.
 *
 * @see docs/CONTENT-MODEL.md
 */
abstract class ContentSettingsMigration extends SettingsMigration
{
    /**
     * Přidá nová pole. Klíč, který už v databázi je, nechá být.
     *
     * Tohle je správná volba pro každou migraci, která rozšiřuje settings
     * třídu o nové pole. Nasazení tím nikomu nic nepřepíše.
     *
     * @param  array<string, mixed>  $values
     */
    protected function add(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! $this->migrator->exists($key)) {
                $this->migrator->add($key, $value);
            }
        }
    }

    /**
     * Přepíše hodnotu bez ohledu na to, co je v databázi.
     *
     * Použitelné jen do spuštění webu, dokud obsah nikdo needituje. Potom
     * se textům radši věnuj v administraci, nebo sáhni po
     * `replaceIfUntouched()`.
     *
     * @param  array<string, mixed>  $values
     */
    protected function replace(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->migrator->exists($key)
                ? $this->migrator->update($key, fn () => $value)
                : $this->migrator->add($key, $value);
        }
    }

    /**
     * Přepíše hodnotu jen tehdy, když v databázi pořád stojí to, co tam
     * naposledy poslala migrace. Když si text mezitím upravil správce,
     * jeho verze zůstane.
     *
     * Hodí se na opravu překlepu ve větě, kterou jsme sami nasadili, na webu,
     * který už žije vlastním obsahem.
     */
    protected function replaceIfUntouched(string $key, mixed $expected, mixed $new): void
    {
        if (! $this->migrator->exists($key)) {
            $this->migrator->add($key, $new);

            return;
        }

        $this->migrator->update($key, fn (mixed $current) => $current === $expected ? $new : $current);
    }
}
