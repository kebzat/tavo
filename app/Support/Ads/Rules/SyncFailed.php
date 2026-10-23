<?php

namespace App\Support\Ads\Rules;

use App\Enums\Ads\AlertSeverity;
use App\Models\Ads\AdAccount;
use App\Support\Ads\Platforms\Platforms;

/**
 * Z účtu se nedaří stahovat čísla: poslední stažení selhalo, nebo data
 * končí dřív než předevčírem (chybí víc než den). Účet propojený před víc
 * než dnem, který se ještě nestáhl, je taky problém. Bez dat ostatní
 * výkonová pravidla mlčí, proto je nahoře.
 */
class SyncFailed implements Rule
{
    public function __construct(private readonly Platforms $platforms) {}

    public function key(): string
    {
        return 'sync_failed';
    }

    public function evaluate(AlertContext $context): array
    {
        $yesterday = $context->yesterday();

        return $context->accounts()
            ->filter(fn (AdAccount $account): bool => $this->platforms->for($account->platform)->isConfigured())
            ->filter(fn (AdAccount $account): bool => filled($account->last_sync_error)
                || ($account->data_through !== null && $account->data_through->lt($yesterday->subDay()))
                || ($account->data_through === null && $account->created_at?->lt($context->today->subDay())))
            ->map(function (AdAccount $account) use ($yesterday): Finding {
                $through = $account->data_through;
                $missing = $through ? (int) $through->diffInDays($yesterday) : null;

                return new Finding(
                    AlertSeverity::Critical,
                    "Nestahují se čísla z účtu {$account->name}",
                    'Zkontrolujte, jestli nám klient účet v Business Manageru nesebral a jestli platí token Meta (Reklamy → Nastavení → Napojení ukazuje výsledek ranního stažení). Po opravě stačí tlačítko Obnovit čísla na detailu klienta, chybějící dny se doplní samy.',
                    ['error' => $account->last_sync_error, 'last_synced_at' => $account->last_synced_at?->toIso8601String(), 'data_through' => $through?->toDateString()],
                    $account,
                    facts: collect([
                        $account->last_sync_error ? 'Poslední chyba: '.$account->last_sync_error : null,
                        $through ? 'Data máme kompletní do '.$through->format('j. n. Y').($missing > 0 ? ', chybí '.$missing.' '.($missing < 5 ? 'dny' : 'dní').'.' : '.') : 'Účet se ještě ani jednou nestáhl.',
                        'Dokud data chybí, nehlídáme rozpočet ani výkon a srovnání neukazujeme.',
                    ])->filter()->implode(' '),
                );
            })
            ->values()
            ->all();
    }
}
