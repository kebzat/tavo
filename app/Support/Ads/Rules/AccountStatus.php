<?php

namespace App\Support\Ads\Rules;

use App\Enums\Ads\AlertSeverity;
use App\Models\Ads\AdAccount;

/** Účet je zablokovaný, nezaplacený nebo v kontrole. Reklamy v tu chvíli neběží. */
class AccountStatus implements Rule
{
    public function key(): string
    {
        return 'account_status';
    }

    public function evaluate(AlertContext $context): array
    {
        return $context->accounts()
            ->filter(fn (AdAccount $account): bool => $account->status !== null && ! in_array($account->status, AdAccount::OK_STATUSES, true))
            ->map(fn (AdAccount $account): Finding => new Finding(
                AlertSeverity::Critical,
                "Účet {$account->name}: {$account->statusLabel()}",
                match ($account->status) {
                    'unsettled', 'grace_period', 'pending_settlement' => 'Klient má u Mety nezaplacenou reklamu a reklamy stojí. Napište mu, ať ve Správci reklam v části Fakturace uhradí dluh nebo vymění platební kartu.',
                    'disabled' => 'Meta účet zablokovala. Důvod je ve Správci reklam v Kvalitě účtu. Když jde o omyl, požádejte tam o kontrolu. Klientovi dejte vědět, že reklamy neběží.',
                    'pending_risk_review' => 'Meta účet prověřuje a reklamy můžou stát. Obvykle to trvá den až dva. Když se nic nezmění, ozvěte se podpoře Mety.',
                    default => 'Účet se nechová standardně. Zkontrolujte ho ve Správci reklam.',
                },
                ['status' => $account->status],
                $account,
            ))
            ->values()
            ->all();
    }
}
