<?php

use App\Support\ContentSettingsMigration;

/**
 * Místo pro společnou fotku na stránkách „Potenciální spolupráce".
 * Všechno prázdné: sekce se ukáže, až správce nahraje fotku.
 */
return new class extends ContentSettingsMigration
{
    public function up(): void
    {
        $this->add([
            'proposal.photo' => null,
            'proposal.photo_alt' => null,
            'proposal.photo_title' => null,
            'proposal.photo_text' => null,
        ]);
    }
};
