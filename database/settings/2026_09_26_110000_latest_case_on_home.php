<?php

use App\Support\ContentSettingsMigration;

/**
 * Sekce „Nejnovější projekt" pod úvodem homepage. Kterou referenci ukáže,
 * vybírá správce v Nastavení → Homepage → Služby a reference. Svět Cejlonu
 * do pole zapíše migrace, která tu referenci zakládá.
 */
return new class extends ContentSettingsMigration
{
    public function up(): void
    {
        $this->add([
            'home.latest_case_id' => null,
        ]);
    }
};
