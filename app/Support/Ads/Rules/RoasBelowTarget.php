<?php

namespace App\Support\Ads\Rules;

use App\Enums\Ads\AlertSeverity;
use App\Enums\Ads\PrimaryGoal;
use App\Support\Ads\Format;

/** ROAS za poslední týden je pod cílem. Jen u e-shopů a nad minimem nákupů. */
class RoasBelowTarget implements Rule
{
    public function key(): string
    {
        return 'roas_below_target';
    }

    public function evaluate(AlertContext $context): array
    {
        $target = $context->clientSettings()?->target_roas;

        if ($context->goal() !== PrimaryGoal::Purchases || ! $target) {
            return [];
        }

        $week = $context->totals($context->week());
        $roas = $week->roas();

        if ($week->get('purchases') < $context->settings->min_conversions || $roas === null) {
            return [];
        }

        if ($roas >= $target * (1 - $context->settings->roas_under_pct / 100)) {
            return [];
        }

        $currency = $context->currency();

        return [new Finding(
            AlertSeverity::Warning,
            'ROAS '.Format::roas($roas).' je pod cílem '.Format::roas($target),
            'Za posledních 7 dní přinesly reklamy nákupy za '.Format::money($week->get('purchase_value'), $currency)
                .' při útratě '.Format::money($week->spend(), $currency).'.'
                ."\n\nOvěřte, jestli neklesla průměrná objednávka (slevy, levnější sortiment v reklamách) a které kampaně ROAS stahují. Připomeňte klientovi, že ROAS z Mety je atribuce platformy, ne zisk.",
            ['roas' => $roas, 'target' => $target],
        )];
    }
}
