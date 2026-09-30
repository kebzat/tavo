<?php

namespace App\Support\Ads\Rules;

use App\Enums\Ads\AlertSeverity;
use App\Enums\Ads\PrimaryGoal;
use App\Support\Ads\Format;

/**
 * Cena za konverzi za poslední týden je nad cílem. Hodnotí se jen nad
 * minimem konverzí, u malého účtu dělá jedna objednávka navíc nebo
 * míň rozdíl desítek procent.
 */
class CostAboveTarget implements Rule
{
    public function key(): string
    {
        return 'cost_above_target';
    }

    public function evaluate(AlertContext $context): array
    {
        $goal = $context->goal();
        $target = $context->clientSettings()?->target_cpa;

        if ($goal === PrimaryGoal::Traffic || ! $target) {
            return [];
        }

        $week = $context->totals($context->week());
        $conversions = $week->conversions($goal);
        $cost = $week->costPerConversion($goal);

        if ($conversions < $context->settings->min_conversions || $cost === null) {
            return [];
        }

        $limit = $target * (1 + $context->settings->cpa_over_pct / 100);

        if ($cost <= $limit) {
            return [];
        }

        $currency = $context->currency();

        return [new Finding(
            AlertSeverity::Warning,
            $goal->costLabel().' '.Format::unitPrice($cost, $currency).' je nad cílem '.Format::unitPrice($target, $currency),
            'Za posledních 7 dní: '.Format::count($conversions).' '.mb_strtolower($goal->conversionLabel()).' za '.Format::money($week->spend(), $currency).'. '
                ."\n\nPodívejte se, které kampaně a sady cenu táhnou nahoru, a přesuňte rozpočet k těm levnějším. Zkontrolujte i frekvenci a stáří kreativ a jestli se nezměnilo něco na webu (ceny, doprava, pokladna).",
            ['cost' => $cost, 'target' => $target, 'conversions' => $conversions],
        )];
    }
}
