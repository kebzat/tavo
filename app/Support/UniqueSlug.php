<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Čitelná adresa sdíleného dokumentu (audit, checklist, nabídka spolupráce).
 *
 * Z názvu firmy udělá slug a když už ho má jiný záznam téže tabulky, přidá
 * pořadové číslo: druhý audit Světa Cejlonu dostane `svet-cejlonu-2`.
 */
final class UniqueSlug
{
    public static function for(Model $record, ?string $source, string $fallback = 'klient'): string
    {
        // Tečka by ze „melichar.cz“ udělala „melicharcz“.
        $base = Str::slug(str_replace('.', ' ', (string) $source)) ?: $fallback;
        $slug = $base;
        $suffix = 2;

        while (self::taken($record, $slug)) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * Má tabulka už sloupec `slug`? Starší datové migrace zakládají checklisty
     * a audity přes model ještě před migrací, která sloupec přidala. Hook
     * modelu by jim jinak slug vnutil a na čisté databázi spadly.
     *
     * Pamatujeme si jen kladnou odpověď: během jednoho `migrate` sloupec
     * přibude a další záznamy už slug dostat mají.
     */
    public static function supported(Model $record): bool
    {
        $table = $record->getTable();

        return (self::$withSlug[$table] ??= (Schema::hasColumn($table, 'slug') ?: null)) === true;
    }

    /** @var array<string, true> */
    private static array $withSlug = [];

    private static function taken(Model $record, string $slug): bool
    {
        return $record->newQuery()
            ->where('slug', $slug)
            ->when($record->exists, fn ($query) => $query->whereKeyNot($record->getKey()))
            ->exists();
    }
}
