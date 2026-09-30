<?php

namespace App\Support\Ads\Rules;

use App\Enums\Ads\AlertSeverity;
use App\Enums\Ads\PrimaryGoal;
use App\Support\Ads\Format;
use App\Support\Ads\Metrics;
use App\Support\Ads\Period;

/**
 * Několik dní útraty bez jediné konverze, přitom předtím konverze chodily.
 * Nejčastěji to znamená rozbité měření po úpravě webu, ne špatné reklamy.
 */
class SpendWithoutConversions implements Rule
{
    public function key(): string
    {
        return 'no_conversions';
    }

    public function evaluate(AlertContext $context): array
    {
        $goal = $context->goal();

        if ($goal === PrimaryGoal::Traffic) {
            return [];
        }

        $days = max(1, $context->settings->no_conversion_days);
        $recent = $context->lastDays($days);
        $daily = $context->daily($recent);

        $everyDaySpent = collect($daily)->every(fn (Metrics $day): bool => $day->spend() > 0);
        $totals = $context->totals($recent);

        if (! $everyDaySpent || $totals->conversions($goal) > 0) {
            return [];
        }

        $before = $context->totals(Period::between($recent->from->subDays(28), $recent->from->subDay()));

        if ($before->conversions($goal) <= 0) {
            return [];
        }

        return [new Finding(
            AlertSeverity::Critical,
            $days.($days < 5 ? ' dny' : ' dní').' útrata bez jediné konverze',
            'Za posledních '.$days.' dní se utratilo '.Format::money($totals->spend(), $context->currency())
                .' a nepřišla žádná konverze ('.mb_strtolower($goal->conversionLabel()).'). Předtím chodily ('.Format::count($before->conversions($goal)).' za 4 týdny).'
                ."\n\nNejdřív zkontrolujte měření: jestli se na webu spouští pixel a událost konverze (Meta Pixel Helper, Správce událostí) a jestli se nedávno neměnil web nebo pokladna. Když měření funguje, jde o reklamy.",
            ['spend' => $totals->spend(), 'previous_conversions' => $before->conversions($goal)],
        )];
    }
}
