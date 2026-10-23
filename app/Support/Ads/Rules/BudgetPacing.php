<?php

namespace App\Support\Ads\Rules;

use App\Enums\Ads\AlertSeverity;
use App\Support\Ads\BudgetPace;
use App\Support\Ads\Format;

/**
 * Měsíční rozpočet (klienta nebo skupiny kampaní) se čerpá moc pomalu
 * nebo moc rychle. Prvních pět dní v měsíci mlčí, kampaně se rozjíždějí
 * a poměr by kolísal. Počítá se jen z kompletních dní.
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
        $pace = BudgetPace::for(
            $context->accountIds(),
            $context->monthlyBudget(),
            $context->today,
            $context->dataStatus()->completeThrough,
            $context->scope->campaignIds(),
        );

        if ($pace === null || $pace->partial || $pace->daysElapsed < self::QUIET_DAYS || $pace->pacePercent() === null) {
            return [];
        }

        $currency = $context->currency();
        $percent = $pace->pacePercent();
        $facts = 'Utraceno '.Format::money($pace->spent, $currency).' z '.Format::money($pace->budget, $currency)
            .' za '.$pace->daysElapsed.' dní, podle plánu by mělo být '.Format::money($pace->expected, $currency)
            .' ('.Format::number($percent).' % plánu). Při tomhle tempu vyjde měsíc na '.Format::money($pace->projected(), $currency).'.';
        $basis = 'Útrata od 1. do '.$pace->through?->format('j. n.').', rozpočet '.Format::money($pace->budget, $currency).' na měsíc';
        $snapshot = [
            'budget' => $pace->budget,
            'spent' => $pace->spent,
            'expected' => $pace->expected,
            'projected' => $pace->projected(),
        ];

        if ($percent < $context->threshold('budget_under_pct')) {
            return [new Finding(
                AlertSeverity::Warning,
                'Rozpočet se čerpá pomalu ('.Format::number($percent).' % plánu)',
                'Zkontrolujte, jestli kampaně nejsou omezené nabídkou, úzkým publikem nebo zamítnutými reklamami. Když má klient utratit celý rozpočet, přidejte denní rozpočet kampaním, které fungují.',
                $snapshot,
                facts: $facts,
                basis: $basis,
            )];
        }

        if ($percent > $context->threshold('budget_over_pct')) {
            $projected = $pace->projected() ?? 0;
            $meets = $context->meetsTarget($context->totals($context->week()));

            return [new Finding(
                $projected > $pace->budget * 1.25 && $meets !== true ? AlertSeverity::Critical : AlertSeverity::Warning,
                'Hrozí přečerpání rozpočtu ('.Format::number($percent).' % plánu)',
                ($meets === true ? 'Kampaně přitom plní cíl, přečerpání se může vyplatit. ' : '')
                    .'Snižte denní rozpočty, nebo se s klientem domluvte na navýšení, pokud se kampaně vyplácejí.',
                $snapshot,
                facts: $facts,
                basis: $basis,
            )];
        }

        return [];
    }
}
