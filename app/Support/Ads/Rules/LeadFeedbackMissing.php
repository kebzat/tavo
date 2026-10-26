<?php

namespace App\Support\Ads\Rules;

use App\Enums\Ads\AlertSeverity;
use App\Support\Ads\Format;

/**
 * Leadová kampaň bez zpětné vazby od klienta. Levný lead nemusí být dobrý
 * lead: bez vědomí, kolik se jich ozvalo a kolik skončilo obchodem, se
 * kampaň nedá poctivě hodnotit.
 */
class LeadFeedbackMissing implements Rule
{
    private const MAX_AGE_DAYS = 21;

    private const MIN_LEADS = 5;

    public function key(): string
    {
        return 'lead_feedback';
    }

    public function evaluate(AlertContext $context): array
    {
        if (! $context->goal()->countsLeads()) {
            return [];
        }

        $leads = $context->totals($context->lastDays(30))->get('leads');
        $latest = $context->client->leadFeedback()->first();

        if ($leads < self::MIN_LEADS || ($latest && $latest->period_to->gte($context->today->subDays(self::MAX_AGE_DAYS)))) {
            return [];
        }

        return [new Finding(
            AlertSeverity::Info,
            'Chybí zpětná vazba ke kvalitě leadů',
            'Vyžádejte si od klienta čísla (dovolali se, relevantní, obchody, hodnota) a zapište je u klienta v části Kvalita leadů.',
            ['leads_30d' => $leads],
            facts: 'Za 30 dní přišlo '.Format::count($leads).' leadů, ale '.($latest ? 'poslední zpětná vazba je do '.$latest->period_to->format('j. n.') : 'od klienta zatím žádná zpětná vazba není').'. '
                .'Bez ní nevíme, kolik leadů bylo relevantních a kolik skončilo obchodem.',
        )];
    }
}
