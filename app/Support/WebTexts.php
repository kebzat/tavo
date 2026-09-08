<?php

namespace App\Support;

use App\Models\WebText;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Statické texty webu editovatelné v administraci.
 *
 * V šabloně se píše `text('reference.perex', 'Výchozí znění.')`. Výchozí znění
 * zůstává v kódu, takže stránka nikdy nezůstane prázdná a v repozitáři je vidět,
 * co na webu stojí. Při prvním vykreslení se klíč sám zapíše do databáze a od
 * té chvíle ho jde přepsat v administraci pod Nastavení → Statické texty.
 *
 * Smazání textu v administraci vrátí výchozí znění z kódu: klíč se při dalším
 * vykreslení založí znovu.
 */
final class WebTexts
{
    private const CACHE_KEY = 'web-texty';

    /** Klíče zapsané v tomhle požadavku, ať se nezakládají dvakrát. */
    private static array $registered = [];

    /**
     * @param  string  $key  klíč v podobě `sekce.nazev`
     * @param  string  $default  znění, které platí, dokud ho správce nepřepíše
     * @param  string|null  $group  název skupiny v administraci
     * @param  string|null  $note  kde na webu text je
     */
    public static function get(string $key, string $default, ?string $group = null, ?string $note = null): string
    {
        $texts = self::all();

        if (array_key_exists($key, $texts)) {
            return $texts[$key];
        }

        self::register($key, $default, $group, $note);

        return $default;
    }

    /** @return array<string, string> */
    public static function all(): array
    {
        try {
            return Cache::rememberForever(
                self::CACHE_KEY,
                fn (): array => WebText::query()->pluck('value', 'key')->all(),
            );
        } catch (Throwable) {
            // Web se nesmí rozsypat kvůli textům: bez databáze (instalace,
            // chybová stránka) platí výchozí znění z kódu.
            return [];
        }
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        self::$registered = [];
    }

    /**
     * Založí klíč, který v databázi ještě není. `insertOrIgnore` proto, že
     * stejnou stránku může naráz vykreslovat víc požadavků.
     */
    private static function register(string $key, string $default, ?string $group, ?string $note): void
    {
        if (isset(self::$registered[$key])) {
            return;
        }

        self::$registered[$key] = true;

        try {
            if (! Schema::hasTable('web_texts')) {
                return;
            }

            DB::table('web_texts')->insertOrIgnore([
                'key' => $key,
                'group' => $group ?: Str::headline(Str::before($key, '.')),
                'note' => $note,
                'value' => $default,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Cache::forget(self::CACHE_KEY);
        } catch (Throwable) {
            // Zápis nesmí shodit vykreslení stránky. Text se založí příště.
        }
    }
}
