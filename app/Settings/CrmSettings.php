<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Provozní nastavení CRM. Edituje se v panelu nástrojů: CRM → Nastavení.
 */
class CrmSettings extends Settings
{
    /** Nabídka rychlých odkladů u aktivity, ve dnech. */
    public array $follow_up_days;

    /** Komu chodí ranní souhrn. Prázdné = všem účtům v CRM. */
    public array $digest_recipients;

    /** Naše IP adresy. Otevření sdíleného dokumentu z nich se klientovi nepočítá. */
    public array $ignored_ips;

    public static function group(): string
    {
        return 'crm';
    }
}
