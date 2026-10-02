<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Záloha před nasazením. Když ji nejde udělat, musí příkaz skončit chybou:
 * nasazení se tím zastaví dřív, než migrace na něco sáhne.
 */
class DbBackupTest extends TestCase
{
    public function test_na_nepodporovane_databazi_skonci_chybou(): void
    {
        // Testy běží na SQLite, mysqldump na ni nepatří.
        $this->artisan('db:zaloha')
            ->expectsOutputToContain('Záloha umí jen MySQL/MariaDB')
            ->assertFailed();
    }
}
