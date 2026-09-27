<?php

namespace App\Console\Commands;

use App\Support\Crm\Scout\Findings;
use App\Support\Crm\Scout\WebScout;
use Illuminate\Console\Command;

/**
 * Změří web a vypíše měření s nálezy jako JSON. Do databáze nic nezapisuje.
 * Používá ho skill /audit-eshopu v Claude Code jako podklad k auditu.
 */
class CrmMeasure extends Command
{
    protected $signature = 'crm:measure {web : Adresa nebo doména webu}';

    protected $description = 'Změří web a vypíše měření a nálezy jako JSON (nic neukládá)';

    public function handle(WebScout $web): int
    {
        $url = preg_match('~^https?://~', $this->argument('web')) ? $this->argument('web') : 'https://'.$this->argument('web');
        $m = $web->measure($url);

        $this->line((string) json_encode([
            'measurements' => $m,
            'findings' => Findings::from($m),
            'passed' => Findings::passed($m),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $m['reachable'] ? self::SUCCESS : self::FAILURE;
    }
}
