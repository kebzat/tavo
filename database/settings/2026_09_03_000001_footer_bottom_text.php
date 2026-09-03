<?php

use App\Support\ContentSettingsMigration;

/**
 * Volitelný řádek textu pod spodní lištou patičky.
 *
 * Výchozí hodnota je prázdná schválně: co tam bude stát, si napíše správce
 * v administraci (Nastavení → Web, sekce Patička). Dokud pole nevyplní,
 * patička vypadá přesně jako dosud.
 */
return new class extends ContentSettingsMigration
{
    public function up(): void
    {
        $this->add([
            'site.footer_bottom_text' => null,
        ]);
    }
};
