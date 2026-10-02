<?php

namespace App\Support\Ads\Platforms;

/** Dosah účtu za celé období, jak ho spočítala platforma. Přes dny se nesčítá. */
final class ReachStat
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly int $reach = 0,
        public readonly int $impressions = 0,
        public readonly float $frequency = 0,
        public readonly int $uniqueLinkClicks = 0,
    ) {}
}
