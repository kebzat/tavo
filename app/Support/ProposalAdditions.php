<?php

namespace App\Support;

use App\Models\Proposal;

/**
 * Doplnění bodů do existující nabídky spolupráce z migrace, bez přepsání
 * toho, co mezitím upravili v nástrojích.
 *
 * Položka se stejným nadpisem, jaký už v sekci je, se přeskočí. Klíč
 * `_after` s nadpisem existující položky ji vloží hned za ni, jinak jde
 * na konec. Nic se nemaže.
 */
class ProposalAdditions
{
    /**
     * @param  array{findings?: list<array<string, mixed>>, recommendations?: list<array<string, mixed>>, steps?: list<array<string, mixed>>}  $sections
     */
    public static function apply(string $slug, array $sections): ?Proposal
    {
        $proposal = Proposal::where('slug', $slug)->first();

        if (! $proposal) {
            return null;
        }

        $proposal->update(collect($sections)
            ->map(fn (array $items, string $section): array => self::merge($proposal->{$section} ?? [], $items))
            ->all());

        return $proposal;
    }

    /**
     * @param  list<array<string, mixed>>  $current
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public static function merge(array $current, array $items): array
    {
        foreach ($items as $item) {
            $after = $item['_after'] ?? null;
            unset($item['_after']);

            if (in_array($item['title'], array_column($current, 'title'), true)) {
                continue;
            }

            $index = $after === null ? false : array_search($after, array_column($current, 'title'), true);
            array_splice($current, $index === false ? count($current) : $index + 1, 0, [$item]);
        }

        return array_values($current);
    }
}
