<?php

namespace App\Support\Ads\Rules;

use App\Enums\Ads\AlertSeverity;
use App\Models\Marketing\LandingCheck;

/**
 * Cílová stránka neodpovídá. Kontrolu dělá php artisan marketing:landing-check
 * (ráno, jeden dotaz na adresu). Chybějící pixel hlídá LandingPixelMissing:
 * upozornění se párují podle pravidla, dvě věci pod jedním klíčem by se přepsaly.
 */
class LandingPageProblem implements Rule
{
    public function key(): string
    {
        return 'landing_page';
    }

    public function evaluate(AlertContext $context): array
    {
        $checks = $context->client->landingChecks()
            ->where('checked_at', '>=', $context->today->subDays(2))
            ->whereIn('url', $context->client->landingUrls())
            ->get();

        $down = $checks->where('ok', false);
        $findings = [];

        if ($down->isNotEmpty()) {
            $findings[] = new Finding(
                AlertSeverity::Critical,
                'Cílová stránka nefunguje ('.$down->count().')',
                "Ranní kontrola nenačetla tyto stránky:\n"
                    .$down->map(fn (LandingCheck $check): string => '- '.$check->url.': '.($check->error ?: 'HTTP '.$check->status_code))->implode("\n")
                    ."\n\nReklamy na ně posílají lidi zbytečně. Ověřte stránku v prohlížeči, a když nejde, zastavte kampaně, které na ni vedou, a dejte vědět Tomovi.",
                ['urls' => $down->pluck('url')->all()],
            );
        }

        return $findings;
    }
}
