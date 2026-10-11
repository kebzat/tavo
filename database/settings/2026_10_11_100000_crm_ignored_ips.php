<?php

use App\Support\ContentSettingsMigration;

/**
 * Naše IP adresy, které se nepočítají do otevření sdílených dokumentů.
 */
return new class extends ContentSettingsMigration
{
    public function up(): void
    {
        $this->add([
            'crm.ignored_ips' => [],
        ]);
    }
};
