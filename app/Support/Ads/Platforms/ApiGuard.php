<?php

namespace App\Support\Ads\Platforms;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Pojistka proti přetížení reklamních API a zablokování účtu Taveo.
 *
 * Každý dotaz na platformu projde přes before(). Pojistka:
 * - počítá dotazy za den a po dosažení denního stropu (config ads.{platforma}.daily_call_limit)
 *   další dotazy do půlnoci odmítne,
 * - když platforma hlásí vytížení (hlavičky X-Business-Use-Case-Usage, X-Ad-Account-Usage
 *   nad 75 %) nebo vrátí chybu „moc dotazů“, stahování z ní pozastaví, podle Mety
 *   do obnovení přístupu, jinak do zítřka.
 *
 * Běžný provoz je o řád níž: ranní synchronizace dělá tři dotazy na účet a den
 * (stav účtu, čísla po kampaních, dosah za přednastavená období).
 * Pojistka je tu pro případ chyby v kódu nebo opakovaného klikání, ne pro každodenní
 * provoz.
 */
final class ApiGuard
{
    /** Od kolika procent vytížení podle Mety přestaneme volat. */
    public const USAGE_LIMIT_PCT = 75;

    private function __construct(private readonly string $platform) {}

    public static function for(string $platform): self
    {
        return new self($platform);
    }

    /**
     * Zavolat před každým dotazem. Když je platforma pozastavená nebo je vyčerpaný
     * denní strop, vyhodí výjimku a dotaz neodejde.
     *
     * @throws AdsApiException
     */
    public function before(): void
    {
        if ($until = $this->pausedUntil()) {
            throw new AdsApiException($this->label().': stahování je pozastavené do '.$until->format('j. n. H:i').' ('.Cache::get($this->key('pause_reason'), 'ochrana před přetížením').').');
        }

        $limit = $this->dailyLimit();

        if ($this->callsToday() >= $limit) {
            throw new AdsApiException($this->label().": dnešní strop {$limit} dotazů je vyčerpaný, další stahování zítra. Běžně stačí tři dotazy na účet a den, zkontrolujte log.");
        }

        Cache::add($this->counterKey(), 0, now()->addDays(2));
        Cache::increment($this->counterKey());
    }

    /** Pozastaví platformu. Delší pauza, která už platí, se nezkracuje. */
    public function pause(Carbon $until, string $reason): void
    {
        $current = $this->pausedUntil();

        if ($current && $current->gte($until)) {
            return;
        }

        Cache::put($this->key('paused_until'), $until->timestamp, $until);
        Cache::put($this->key('pause_reason'), $reason, $until);

        Log::warning("Reklamy: {$this->label()} pozastaveno do {$until->format('j. n. H:i')}.", ['reason' => $reason]);
    }

    /** Pozastaví do zítřejšího rána, kdy běží další plánovaná synchronizace. */
    public function pauseUntilTomorrow(string $reason): void
    {
        $this->pause(now()->addDay()->startOfDay()->setTime(5, 0), $reason);
    }

    public function pausedUntil(): ?Carbon
    {
        $timestamp = Cache::get($this->key('paused_until'));

        return $timestamp && $timestamp > now()->timestamp ? Carbon::createFromTimestamp($timestamp) : null;
    }

    public function callsToday(): int
    {
        return (int) Cache::get($this->counterKey(), 0);
    }

    public function dailyLimit(): int
    {
        return max(1, (int) config("ads.{$this->platform}.daily_call_limit", 200));
    }

    /**
     * Přečte hlavičky vytížení z odpovědi Mety. Když se některé číslo blíží stropu,
     * pozastaví platformu, podle Mety do obnovení přístupu.
     *
     * @param  array<string, list<string>>  $headers
     */
    public function inspectMetaUsage(array $headers): void
    {
        $headers = array_change_key_case($headers);
        $worst = 0;
        $regainMinutes = 0;

        foreach (['x-business-use-case-usage', 'x-app-usage', 'x-ad-account-usage'] as $name) {
            $data = json_decode($headers[$name][0] ?? '', true);

            if (! is_array($data)) {
                continue;
            }

            // Hlavičky mají různý tvar (plochý objekt, nebo objekt podle účtů se seznamy).
            array_walk_recursive($data, function ($value, $key) use (&$worst, &$regainMinutes): void {
                if (in_array($key, ['call_count', 'total_cputime', 'total_time', 'acc_id_util_pct'], true)) {
                    $worst = max($worst, (float) $value);
                }

                if ($key === 'estimated_time_to_regain_access') {
                    $regainMinutes = max($regainMinutes, (int) $value);
                }
            });
        }

        if ($worst >= self::USAGE_LIMIT_PCT || $regainMinutes > 0) {
            $regainMinutes > 0
                ? $this->pause(now()->addMinutes($regainMinutes + 5), "Meta hlásí vytížení {$worst} %")
                : $this->pauseUntilTomorrow("Meta hlásí vytížení {$worst} %");
        }
    }

    private function label(): string
    {
        return match ($this->platform) {
            'meta' => 'Meta',
            'google_ads' => 'Google Ads',
            'ga4' => 'Google Analytics',
            default => $this->platform,
        };
    }

    private function counterKey(): string
    {
        return $this->key('calls.'.now()->toDateString());
    }

    private function key(string $suffix): string
    {
        return "ads.guard.{$this->platform}.{$suffix}";
    }
}
