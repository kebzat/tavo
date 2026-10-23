<?php

namespace App\Support\Ads\Rules;

use App\Enums\Ads\AdPlatform;
use App\Enums\Ads\AlertSeverity;
use App\Models\Ads\AdAccount;
use App\Models\Marketing\LandingCheck;
use App\Support\Ads\Format;
use App\Support\Ads\Stats;

/**
 * Na cílové stránce klienta s reklamami na Metě chybí Meta pixel (ranní
 * kontrola stránek).
 *
 * Kontrola čte jen zdrojový kód stránky. Pixel nasazený přes Google Tag
 * Manager nebo až po souhlasu s cookies v něm není, přitom měří. Když Meta
 * za poslední týden hlásí z webu události (zobrazení stránky, nákupy,
 * poptávky), pixel zjevně funguje a upozornění by bylo falešné.
 */
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

        $meta = $context->accounts()->filter(fn (AdAccount $account): bool => $account->platform === AdPlatform::Meta);
        $noPixel = $checks->where('ok', true)->where('has_meta_pixel', false);

        if ($meta->isEmpty() || $noPixel->isEmpty()) {
            return [];
        }

        $events = Stats::totals($meta->pluck('id')->all(), $context->week());
        $measured = $events->get('landing_page_views') + $events->get('purchases') + $events->get('leads');

        if ($measured > 0) {
            return [];
        }

        return [new Finding(
            AlertSeverity::Warning,
            'Na cílové stránce chybí Meta pixel',
            'Bez pixelu Meta nevidí nákupy ani poptávky a optimalizuje naslepo. Ověřte to v Meta Pixel Helperu nebo ve Správci událostí. Když pixel jede přes Google Tag Manager, upozornění v kartě klienta ztlumte.',
            ['urls' => $noPixel->pluck('url')->all()],
            facts: "Ve zdrojovém kódu těchto stránek není Meta pixel:\n".$noPixel->map(fn (LandingCheck $check): string => '- '.$check->url)->implode("\n")
                ."\nMeta za posledních 7 dní nehlásí z webu žádné události (zobrazení stránky ".Format::count($events->get('landing_page_views')).', nákupy ani poptávky).',
            basis: 'Ranní kontrola stránek a události z Mety za '.$context->week()->label(),
        )];
    }
}
