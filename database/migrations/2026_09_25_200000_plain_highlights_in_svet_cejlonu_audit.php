<?php

use App\Models\Audit;
use Illuminate\Database\Migrations\Migration;

/**
 * Dlaždice pod hlavičkou auditu Světa Cejlonu srozumitelně pro majitele
 * e-shopu: tři hlavní problémy řečí důsledků a na konec, co s tím. Původní
 * dlaždice mluvily o robotech s chybou 503 a meta popiscích.
 *
 * Přepíše jen známé verze: původní z migrace a tu, kterou na webu někdo
 * upravil na „12 priorit“. Jiné úpravy z administrace zůstanou.
 */
return new class extends Migration
{
    private const AUDIT_TOKEN = 'kb3mcK5MHcNnlO5IcZzBtc4zkjmyfbQq0Tsz05uT';

    private const NEW = [
        ['value' => '25×', 'label' => 'víc adres v sitemapě, než má e-shop skutečných stránek'],
        ['value' => '0 ze 4', 'label' => 'obecných dotazů na čaj a koření, u kterých se e-shop ukázal ve vyhledávání pro AI'],
        ['value' => '4', 'label' => 'nesmyslné ukázkové články na webu, jeden už je ve výsledcích Seznamu'],
        ['value' => '1 týden', 'label' => 'na opravu nejvážnějších chyb od dodání přístupů'],
    ];

    /** Druhá dlaždice v obou známých verzích, ostatní jsou stejné. */
    private const KNOWN_SECOND = [
        ['value' => '1 786', 'label' => 'filtračních stránek (22. 9. jich bylo 228)'],
        ['value' => '12', 'label' => 'priorit'],
    ];

    private const KNOWN_REST = [
        0 => ['value' => '1 974', 'label' => 'adres v sitemapě, užitečných je jen asi 80'],
        2 => ['value' => '2 / 14', 'label' => 'AI robotů dostane chybu 503 (GPTBot, ClaudeBot)'],
        3 => ['value' => '0', 'label' => 'vyplněných meta popisků u kategorií a stránek'],
    ];

    public function up(): void
    {
        $audit = Audit::where('public_token', self::AUDIT_TOKEN)->first();

        if (! $audit || ! $this->isKnown($audit->highlightTiles())) {
            return;
        }

        $audit->update(['highlights' => self::NEW]);
    }

    public function down(): void
    {
        $audit = Audit::where('public_token', self::AUDIT_TOKEN)->first();

        if ($audit && $audit->highlightTiles() === self::NEW) {
            $audit->update(['highlights' => [self::KNOWN_REST[0], self::KNOWN_SECOND[0], self::KNOWN_REST[2], self::KNOWN_REST[3]]]);
        }
    }

    /** @param  list<array{value: string, label: string}>  $tiles */
    private function isKnown(array $tiles): bool
    {
        if (count($tiles) !== 4) {
            return false;
        }

        foreach (self::KNOWN_REST as $i => $tile) {
            if ($tiles[$i] !== $tile) {
                return false;
            }
        }

        return in_array($tiles[1], self::KNOWN_SECOND, true);
    }
};
