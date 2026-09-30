<?php

namespace App\Support\Ads\Rules;

use App\Enums\Ads\AlertSeverity;
use App\Support\Ads\BudgetPace;
use App\Support\Ads\Format;

/**
 * Měsíční rozpočet se čerpá moc pomalu nebo moc rychle. Prvních pět dní
 * v měsíci mlčí, kampaně se rozjíždějí a poměr by kolísal.
 */
class BudgetPacing implements Rule
{
    private const QUIET_DAYS = 5;

    public function key(): string
    {
        return 'budget_pacing';
    }

    public function evaluate(AlertContext $context): array
    {
        $pace = BudgetPace::for($context->accountIds(), $context->clientSettings()?->monthly_budget, $context->today);

        if ($pace === null || $pace->daysElapsed < self::QUIET_DAYS || $pace->pacePercent() === null) {
            return [];
        }

        $currency = $context->currency();
        $percent = $pace->pacePercent();
        $numbers = 'Utraceno '.Format::money($pace->spent, $currency).' z '.Format::money($pace->budget, $currency)
            .', podle plánu by mělo být '.Format::money($pace->expected, $currency)
            .'. Při tomhle tempu vyjde měsíc na '.Format::money($pace->projected(), $currency).'.';
        $snapshot = [
            'budget' => $pace->budget,
            'spent' => $pace->spent,
            'expected' => $pace->expected,
            'projected' => $pace->projected(),
        ];

        if ($percent < $context->settings->budget_under_pct) {
            return [new Finding(
                AlertSeverity::Warning,
                'Rozpočet se čerpá pomalu ('.Format::number($percent).' % plánu)',
                $numbers."\n\nZkontrolujte, jestli kampaně nejsou omezené nabídkou, úzkým publikem nebo zamítnutými reklamami. Když má klient utratit celý rozpočet, přidejte denní rozpočet kampaním, které fungují.",
                $snapshot,
            )];
        }

        if ($percent > $context->settings->budget_over_pct) {
            $projected = $pace->projected() ?? 0;

            return [new Finding(
                $projected > $pace->budget * 1.25 ? AlertSeverity::Critical : AlertSeverity::Warning,
                'Hrozí přečerpání rozpočtu ('.Format::number($percent).' % plánu)',
                $numbers."\n\nSnižte denní rozpočty, nebo se s klientem domluvte na navýšení, pokud se kampaně vyplácejí.",
                $snapshot,
            )];
        }

        return [];
    }
}
