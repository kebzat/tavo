<?php

namespace App\Console\Commands;

use App\Models\Automation;
use App\Models\AutomationEvent;
use App\Support\Automations\ParcelPickup;
use Illuminate\Console\Command;

/**
 * Běh automatizací klientů (každou hodinu v :40). Projde zapnuté, s
 * `--automation=ID` jen jednu. `--dry-run` vypíše, co by udělal, a nic
 * neuloží ani nepošle. Viz docs/AUTOMATIZACE.md.
 */
class AutomationsRun extends Command
{
    protected $signature = 'automations:run
        {--automation= : Jen tahle automatizace (ID), i vypnutá se ohlásí}
        {--dry-run : Jen vypíše, co by se stalo, nic neuloží}';

    protected $description = 'Spustí automatizace klientů (připomínky vyzvednutí zásilek)';

    public function handle(ParcelPickup $engine): int
    {
        $automations = Automation::query()
            ->with('client')
            ->when($this->option('automation'), fn ($query, $id) => $query->whereKey($id), fn ($query) => $query->where('enabled', true))
            ->orderBy('id')
            ->get();

        if ($automations->isEmpty()) {
            $this->info('Žádná zapnutá automatizace.');

            return self::SUCCESS;
        }

        $failed = false;

        foreach ($automations as $automation) {
            $this->line("<options=bold>#{$automation->id} {$automation->client?->name}: {$automation->type->getLabel()}</>");
            $report = $engine->run($automation, (bool) $this->option('dry-run'));

            foreach ($report['warnings'] as $warning) {
                $this->warn('  '.$warning);
            }

            if ($report['error'] !== null) {
                $this->error('  Chyba: '.$report['error']);
                $failed = true;

                continue;
            }

            foreach ($report['created'] as $event) {
                $this->line("  {$event['order']}: ".AutomationEvent::kindLabel($event['kind']).". {$event['detail']}");
            }

            $this->line(sprintf('  Nové zprávy %d, potvrzené %d, chyby %d, na výdejních místech %d.',
                count($report['created']), count($report['confirmed']), count($report['failed']), count($report['parcels'])));

            if ($report['held'] > 0) {
                $this->line("  Mimo hodiny posílání, {$report['held']} změn počká na první běh od {$automation->setting('send_from')}:00.");
            }
        }

        if ($this->option('dry-run')) {
            $this->comment('Nanečisto, nic se neuložilo.');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
