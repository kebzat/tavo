<?php

namespace App\Support\Ads\Platforms;

/** Návštěvnost jednoho kanálu za jeden den, jak ji naměřila analytika. */
final class TrafficStat
{
    public function __construct(
        public readonly string $date,
        public readonly string $channel,
        public readonly int $sessions = 0,
        public readonly int $users = 0,
        public readonly int $engagedSessions = 0,
        public readonly float $keyEvents = 0,
        public readonly float $purchases = 0,
        public readonly float $revenue = 0,
    ) {}

    /** @return array<string, mixed> Sloupce tabulky analytics_daily_stats. */
    public function columns(): array
    {
        return [
            'date' => $this->date,
            'channel' => mb_substr($this->channel, 0, 100),
            'sessions' => $this->sessions,
            'users' => $this->users,
            'engaged_sessions' => $this->engagedSessions,
            'key_events' => $this->keyEvents,
            'purchases' => $this->purchases,
            'revenue' => round($this->revenue, 2),
        ];
    }
}
