<?php

use App\Support\ContentSettingsMigration;

/**
 * Dvě drobnosti, které jen přidávají, nic nepřepisují:
 *
 * - Menu: na konec se připojí „Ceník" (/#cenik), pokud tam odkaz na ceník
 *   ještě není. Ostatní položky zůstanou, jak je má správce.
 * - Pruh s čísly: položka s hodnocením na Googlu dostane odkaz na recenze
 *   (/#recenze), pokud žádný odkaz nemá. Číslo ani popisek se nemění.
 */
return new class extends ContentSettingsMigration
{
    public function up(): void
    {
        if ($this->migrator->exists('site.nav_links')) {
            $this->migrator->update('site.nav_links', function (mixed $links): array {
                $links = $this->toArray($links);

                $hasPricing = collect($links)->contains(fn (array $link) => str_contains((string) ($link['url'] ?? ''), '#cenik'));

                return $hasPricing ? $links : [...$links, ['label' => 'Ceník', 'url' => '/#cenik']];
            });
        }

        if ($this->migrator->exists('home.trust_items')) {
            $this->migrator->update('home.trust_items', fn (mixed $items): array => array_map(
                fn (array $item) => blank($item['url'] ?? null) && str_contains(mb_strtolower((string) ($item['label'] ?? '')), 'googl')
                    ? $item + ['url' => '/#recenze']
                    : $item,
                $this->toArray($items),
            ));
        }
    }

    /**
     * Uložené pole se vrací jako seznam objektů, pro úpravu ho chceme jako
     * seznam asociativních polí.
     *
     * @return array<int, array<string, mixed>>
     */
    private function toArray(mixed $value): array
    {
        return array_values(array_map(
            fn ($item) => (array) $item,
            json_decode(json_encode($value ?? []), true) ?: [],
        ));
    }
};
