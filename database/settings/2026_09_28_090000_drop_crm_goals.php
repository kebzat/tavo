<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Týdenní cíle z CRM pryč (rozhodnutí Toma z 28. 9. 2026). Přehled je jen
 * informativní: koho jsme oslovili a jak zareagoval, ne kolik kdo má oslovit.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        foreach (['outreach', 'follow_ups', 'replies', 'calls', 'proposals', 'demand_replies'] as $goal) {
            $this->migrator->deleteIfExists("crm.goal_{$goal}");
        }
    }
};
