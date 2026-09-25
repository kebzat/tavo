<?php

use App\Models\Audit;
use Illuminate\Database\Migrations\Migration;

/**
 * Druhý pokus o nové dlaždice z 2026_09_25_200000. Na ostrém webu se
 * nepřepsaly: úprava „12 priorit“ z administrace se od našeho znění liší
 * v něčem, co na stránce není vidět (nejspíš mezery). Porovnáváme proto
 * jen čísla v dlaždicích a mezery sjednotíme.
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

    public function up(): void
    {
        $audit = Audit::where('public_token', self::AUDIT_TOKEN)->first();

        if (! $audit) {
            return;
        }

        $values = array_map(
            fn (array $tile): string => trim(preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', $tile['value'])),
            $audit->highlightTiles(),
        );

        $known = count($values) === 4
            && $values[0] === '1 974'
            && in_array($values[1], ['1 786', '12'], true)
            && $values[2] === '2 / 14'
            && $values[3] === '0';

        if ($known) {
            $audit->update(['highlights' => self::NEW]);
        }
    }

    public function down(): void
    {
        // Vracení řeší 2026_09_25_200000.
    }
};
