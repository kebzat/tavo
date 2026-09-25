<?php

use App\Support\ContentSettingsMigration;

/**
 * Spuštění webu pro veřejnost (25. 9. 2026): zapne indexaci vyhledávači.
 *
 * Do teď byl přepínač v Nastavení → SEO a měření vypnutý, takže web posílal
 * noindex a robots.txt zakazoval celý web. Migrace běží jen jednou; kdyby bylo
 * potřeba indexaci znovu vypnout, udělá se to v administraci a tahle migrace
 * to už nepřebije.
 */
return new class extends ContentSettingsMigration
{
    public function up(): void
    {
        $this->replace([
            'seo.indexable' => true,
        ]);
    }
};
