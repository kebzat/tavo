<?php

namespace App\Support\Ads\Rules;

use App\Enums\Ads\AlertSeverity;
use App\Models\Ads\AdAccount;
use App\Support\Ads\Platforms\Platforms;

/** Z účtu se nedaří stahovat čísla. Bez nich ostatní pravidla mlčí, proto je nahoře. */
class SyncFailed implements Rule
{
    public function __construct(private readonly Platforms $platforms) {}

    public function key(): string
    {
        return 'sync_failed';
    }

    public function evaluate(AlertContext $context): array
    {
        return $context->accounts()
            ->filter(fn (AdAccount $account): bool => $this->platforms->for($account->platform)->isConfigured())
            ->filter(fn (AdAccount $account): bool => filled($account->last_sync_error)
                || ($account->last_synced_at !== null && $account->last_synced_at->lt($context->today->subDays(2))))
            ->map(fn (AdAccount $account): Finding => new Finding(
                AlertSeverity::Critical,
                "Nestahují se čísla z účtu {$account->name}",
                ($account->last_sync_error ? "Poslední chyba: {$account->last_sync_error}\n\n" : '')
                    .'Dokud se to nespraví, nehlídáme rozpočet ani výkon. Zkontrolujte, jestli nám klient účet v Business Manageru nesebral a jestli platí token Meta (Reklamy → Nastavení → Otestovat spojení).',
                ['error' => $account->last_sync_error, 'last_synced_at' => $account->last_synced_at?->toIso8601String()],
                $account,
            ))
            ->values()
            ->all();
    }
}
