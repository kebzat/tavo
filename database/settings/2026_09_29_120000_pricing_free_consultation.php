<?php

use App\Support\ContentSettingsMigration;

/**
 * Pruh „Úvodní konzultace zdarma“ pod kartami ceníku (zadání Tomáše, 29. 9. 2026).
 * Ať se lidé nebojí ozvat dřív, než se rozhodnou pro placenou formu spolupráce.
 */
return new class extends ContentSettingsMigration
{
    public function up(): void
    {
        $this->add([
            'home.pricing_free_title' => 'Úvodní konzultace zdarma',
            'home.pricing_free_text' => 'Na úvodní schůzce probereme vaše očekávání, cíle a náš pohled na váš byznys, marketing a web. Nic neplatíte a k ničemu se nezavazujete.',
            'home.pricing_free_cta_label' => 'Domluvit konzultaci',
        ]);
    }
};
