<?php

namespace App\Support\Ads\Rules;

use App\Enums\Ads\AdPlatform;
use App\Enums\Ads\AlertSeverity;
use App\Models\Marketing\LandingCheck;

/** Na cílové stránce klienta s reklamami na Metě chybí Meta pixel (ranní kontrola stránek). */
class LandingPixelMissing implements Rule
{
    public function key(): string
    {
        return 'landing_pixel';
    }

    public function evaluate(AlertContext $context): array
    {
        $checks = $context->client->landingChecks()
            ->where('checked_at', '>=', $context->today->subDays(2))
            ->whereIn('url', $context->client->landingUrls())
            ->get();
        $findings = [];

        $usesMeta = $context->accounts()->contains(fn ($account): bool => $account->platform === AdPlatform::Meta);
        $noPixel = $checks->where('ok', true)->where('has_meta_pixel', false);

        if ($usesMeta && $noPixel->isNotEmpty()) {
            $findings[] = new Finding(
                AlertSeverity::Warning,
                'Na cílové stránce chybí Meta pixel',
                "Ve zdrojovém kódu těchto stránek není Meta pixel:\n"
                    .$noPixel->map(fn (LandingCheck $check): string => '- '.$check->url)->implode("\n")
                    ."\n\nBez pixelu Meta nevidí nákupy ani poptávky a optimalizuje naslepo. Pozor: pixel nasazený přes Google Tag Manager nebo po souhlasu s cookies kontrola nevidí, pak upozornění v kartě klienta ztlumte.",
                ['urls' => $noPixel->pluck('url')->all()],
            );
        }

        return $findings;
    }
}
