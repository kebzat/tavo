<?php

use App\Support\ContentSettingsMigration;

/**
 * Výchozí nastavení CRM. Hodnoty vycházejí z tempa, které si na podzim
 * držíme: dvacet oslovení a patnáct follow-upů týdně.
 */
return new class extends ContentSettingsMigration
{
    public function up(): void
    {
        $this->add([
            'crm.goal_outreach' => 20,
            'crm.goal_follow_ups' => 15,
            'crm.goal_replies' => 4,
            'crm.goal_calls' => 2,
            'crm.goal_proposals' => 2,
            'crm.goal_demand_replies' => 3,
            'crm.follow_up_days' => [3, 7, 14],
            'crm.digest_recipients' => [],
        ]);
    }
};
