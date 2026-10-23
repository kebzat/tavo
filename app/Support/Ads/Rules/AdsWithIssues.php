<?php

namespace App\Support\Ads\Rules;

use App\Enums\Ads\AlertSeverity;
use App\Models\Ads\AdAccount;

/**
 * Zamítnuté nebo problémové reklamy. Seznam stahuje ranní synchronizace
 * jedním dotazem na účet Mety (AdAccount::$issues).
 */
class AdsWithIssues implements Rule
{
    public function key(): string
    {
        return 'ad_issues';
    }

    public function evaluate(AlertContext $context): array
    {
        $findings = [];

        foreach ($context->accounts() as $account) {
            /** @var AdAccount $account */
            $issues = $account->issues ?? [];
            $fresh = $account->issues_checked_at?->gte($context->today->subDays(2));

            if (! $fresh || $issues === []) {
                continue;
            }

            $disapproved = collect($issues)->where('status', 'DISAPPROVED');
            $list = collect($issues)->take(8)->map(fn (array $ad): string => '- '.$ad['name'].' ('.($ad['status'] === 'DISAPPROVED' ? 'zamítnutá' : 's problémem').')'
                .(filled($ad['feedback'] ?? null) ? ': '.$ad['feedback'] : ''))->implode("\n");

            $findings[] = new Finding(
                $disapproved->isNotEmpty() ? AlertSeverity::Warning : AlertSeverity::Info,
                $disapproved->isNotEmpty()
                    ? 'Zamítnuté reklamy ('.$disapproved->count().')'
                    : 'Reklamy s problémem ('.count($issues).')',
                'Otevřete reklamu ve Správci reklam, upravte text nebo kreativu podle zásad, případně požádejte o nové posouzení. Zamítnutá reklama nic neutrácí, ale kampaň může přijít o nejlepší kreativu.',
                ['count' => count($issues), 'disapproved' => $disapproved->count()],
                $account,
                facts: "Meta u těchto reklam hlásí problém:\n".$list,
                basis: 'Ranní kontrola reklam '.$account->issues_checked_at?->format('j. n. G:i'),
            );
        }

        return $findings;
    }
}
