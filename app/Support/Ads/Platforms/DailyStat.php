<?php

namespace App\Support\Ads\Platforms;

/** Čísla jedné kampaně za jeden den, převedená do společného tvaru. */
final class DailyStat
{
    /** @param  array<string, mixed>  $raw */
    public function __construct(
        public readonly string $campaignId,
        public readonly string $campaignName,
        public readonly ?string $objective,
        public readonly string $date,
        public readonly float $spend = 0,
        public readonly int $impressions = 0,
        public readonly int $reach = 0,
        public readonly int $clicks = 0,
        public readonly int $linkClicks = 0,
        public readonly float $purchases = 0,
        public readonly float $purchaseValue = 0,
        public readonly float $leads = 0,
        public readonly float $addToCart = 0,
        public readonly float $checkouts = 0,
        public readonly array $raw = [],
    ) {}

    /** @return array<string, mixed> Sloupce tabulky ad_daily_stats. */
    public function columns(): array
    {
        return [
            'date' => $this->date,
            'spend' => round($this->spend, 2),
            'impressions' => $this->impressions,
            'reach' => $this->reach,
            'clicks' => $this->clicks,
            'link_clicks' => $this->linkClicks,
            'purchases' => $this->purchases,
            'purchase_value' => round($this->purchaseValue, 2),
            'leads' => $this->leads,
            'add_to_cart' => $this->addToCart,
            'checkouts' => $this->checkouts,
        ];
    }
}
