<?php

namespace App\Console\Commands;

use App\Mail\AutomationSummary;
use App\Models\Automation;
use App\Models\AutomationEvent;
use App\Support\Automations\AutomationAlerts;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Ranní souhrn zapnutých automatizací pro účty klientů (v 7:00). */
class AutomationsSummary extends Command
{
    protected $signature = 'automations:summary {--dry-run : Jen vypíše, komu by souhrn šel}';

    protected $description = 'Pošle klientům ranní souhrn automatizací za včerejšek';

    public function handle(): int
    {
        $yesterday = CarbonImmutable::now('Europe/Prague')->subDay();
        $from = $yesterday->startOfDay()->utc();
        $to = $yesterday->endOfDay()->utc();

        foreach (Automation::query()->with('client')->where('enabled', true)->orderBy('id')->get() as $automation) {
            $users = AutomationAlerts::clientUsers($automation);

            if ($users->isEmpty()) {
                continue;
            }

            $sent = [];
            $orders = [];

            foreach (AutomationEvent::KINDS as $kind) {
                $numbers = $automation->events()->where('kind', $kind)->where('state', AutomationEvent::STATE_CONFIRMED)->whereBetween('confirmed_at', [$from, $to])->orderBy('order_number')->pluck('order_number')->all();

                if ($numbers !== []) {
                    $sent[AutomationEvent::kindLabel($kind)] = count($numbers);
                    $orders[AutomationEvent::kindLabel($kind)] = $numbers;
                }
            }

            $failed = $automation->events()->where('state', AutomationEvent::STATE_FAILED)->whereBetween('updated_at', [$from, $to])->count();

            foreach ($users as $user) {
                if ($this->option('dry-run')) {
                    $this->line("Souhrn #{$automation->id} by šel uživateli {$user->name}.");

                    continue;
                }

                try {
                    Mail::to($user->email)->send(new AutomationSummary($automation, $user, $yesterday->format('j. n.'), $sent, $failed, count($automation->parcels()), $orders));
                } catch (Throwable $e) {
                    Log::error('Ranní souhrn automatizace se nepodařilo odeslat.', ['automation' => $automation->getKey(), 'user' => $user->getKey(), 'error' => $e->getMessage()]);
                }
            }
        }

        return self::SUCCESS;
    }
}
