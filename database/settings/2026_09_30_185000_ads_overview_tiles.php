<?php

use App\Support\ContentSettingsMigration;

/**
 * Šest čísel na kartě klienta v přehledu reklam. Mění se třemi tečkami
 * u dlaždice a platí pro všechny.
 *
 * Datum je před migrací s ukázkovými klienty, která AdsSettings načítá
 * (bez tohohle pole by na čisté databázi spadla).
 */
return new class extends ContentSettingsMigration
{
    public function up(): void
    {
        $this->add([
            'ads.overview_tiles' => ['spend', 'conversions', 'cost_per_conversion', 'roas', 'ctr', 'cpm'],
        ]);
    }
};
