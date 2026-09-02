<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Provozní nastavení CRM. Edituje se v panelu nástrojů: CRM → Nastavení.
 *
 * Týdenní cíle tu nejsou proto, aby se plnily tabulky, ale aby přehled uměl
 * říct „tenhle týden chybí pět oslovení" bez počítání v hlavě.
 */
class CrmSettings extends Settings
{
    /** Nová oslovení za týden. */
    public int $goal_outreach;

    /** Follow-upy za týden. */
    public int $goal_follow_ups;

    /** Odpovědi od oslovených. */
    public int $goal_replies;

    /** Hovory a schůzky. */
    public int $goal_calls;

    /** Odeslané nabídky. */
    public int $goal_proposals;

    /** Reakce na poptávky z portálů. */
    public int $goal_demand_replies;

    /** Nabídka rychlých odkladů u aktivity, ve dnech. */
    public array $follow_up_days;

    /** Komu chodí ranní souhrn. Prázdné = všem účtům v CRM. */
    public array $digest_recipients;

    public static function group(): string
    {
        return 'crm';
    }

    /**
     * Cíle v pořadí, v jakém je vypisuje přehled. Klíč odpovídá klíči
     * ve výsledku App\Support\Crm\WeeklyKpi — díky tomu se obě strany
     * spárují bez dalšího převodníku.
     *
     * @return array<string, int>
     */
    public function goals(): array
    {
        return [
            'outreach' => $this->goal_outreach,
            'follow_ups' => $this->goal_follow_ups,
            'replies' => $this->goal_replies,
            'calls' => $this->goal_calls,
            'proposals' => $this->goal_proposals,
            'demand_replies' => $this->goal_demand_replies,
        ];
    }
}
