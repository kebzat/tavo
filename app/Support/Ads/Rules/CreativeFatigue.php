<?php

namespace App\Support\Ads\Rules;

use App\Enums\Ads\AlertSeverity;
use App\Support\Ads\Format;

/**
 * Reklamy se okoukaly: CTR padá proti předchozímu týdnu nebo je vysoká
 * frekvence. Frekvence je ze součtu denních dosahů, tedy spíš nižší než
 * skutečná, viz Metrics::frequency().
 */
class CreativeFatigue implements Rule
{
    /** Pod tímhle počtem zobrazení za týden je CTR náhoda. */
    private const MIN_IMPRESSIONS = 5000;

    public function key(): string
    {
        return 'creative_fatigue';
    }

    public function evaluate(AlertContext $context): array
    {
        $week = $context->totals($context->week());
        $previous = $context->totals($context->week()->previous());

        if ($week->get('impressions') < self::MIN_IMPRESSIONS) {
            return [];
        }

        $ctr = $week->ctr();
        $previousCtr = $previous->get('impressions') >= self::MIN_IMPRESSIONS ? $previous->ctr() : null;
        $frequency = $week->frequency();

        $ctrDropped = $ctr !== null && $previousCtr !== null && $previousCtr > 0
            && ($previousCtr - $ctr) / $previousCtr * 100 >= $context->settings->ctr_drop_pct;
        $tooFrequent = $frequency !== null && $frequency >= $context->settings->frequency_max;

        if (! $ctrDropped && ! $tooFrequent) {
            return [];
        }

        $facts = array_filter([
            $ctrDropped ? 'CTR kleslo z '.Format::percent($previousCtr).' na '.Format::percent($ctr).'.' : null,
            $tooFrequent ? 'Frekvence je aspoň '.Format::number($frequency, 1).', lidé vidí stejné reklamy opakovaně.' : null,
        ]);

        return [new Finding(
            AlertSeverity::Info,
            'Reklamy se okoukávají',
            implode(' ', $facts)."\n\nPřipravte nové kreativy (jiný úvodní záběr, jiný úhel sdělení) a vypněte ty s nejnižším CTR. U malého publika zvažte rozšíření cílení.",
            ['ctr' => $ctr, 'previous_ctr' => $previousCtr, 'frequency' => $frequency],
        )];
    }
}
