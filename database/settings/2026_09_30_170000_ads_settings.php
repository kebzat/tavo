<?php

use App\Support\ContentSettingsMigration;

/**
 * Výchozí prahy upozornění u reklam. Nastavené tak, aby malé účty
 * s pár tisíci měsíčně nesvítily kvůli běžnému kolísání.
 */
return new class extends ContentSettingsMigration
{
    public function up(): void
    {
        $this->add([
            'ads.digest_recipients' => [],
            'ads.budget_under_pct' => 80,
            'ads.budget_over_pct' => 110,
            'ads.cpa_over_pct' => 30,
            'ads.roas_under_pct' => 30,
            'ads.ctr_drop_pct' => 30,
            'ads.frequency_max' => 3.5,
            'ads.no_conversion_days' => 3,
            'ads.spend_spike_pct' => 50,
            'ads.min_conversions' => 10,
        ]);
    }
};
