<?php

namespace App\Models\Ads;

use App\Enums\Ads\AdPlatform;
use App\Models\Client;
use App\Support\Ads\Period;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Reklamní účet klienta, který nám nasdílel jako partnerovi.
 * Tokeny u něj nejsou, čteme ho přístupem Taveo z config/ads.php.
 */
class AdAccount extends Model
{
    protected $guarded = [];

    /** Stavy, se kterými účet normálně jede. Ostatní hlásíme jako upozornění. */
    public const OK_STATUSES = ['active'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(AdCampaign::class);
    }

    public function stats(): HasMany
    {
        return $this->hasMany(AdDailyStat::class);
    }

    public function traffic(): HasMany
    {
        return $this->hasMany(AnalyticsDailyStat::class);
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(AdSyncRun::class);
    }

    public function latestSyncRun(): HasOne
    {
        return $this->hasOne(AdSyncRun::class)->latestOfMany('started_at');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Reklamní účty, bez analytiky. */
    public function scopeAds(Builder $query): Builder
    {
        return $query->whereIn('platform', AdPlatform::adPlatforms());
    }

    public function scopeAnalytics(Builder $query): Builder
    {
        return $query->whereIn('platform', AdPlatform::analyticsPlatforms());
    }

    public function isAnalytics(): bool
    {
        return $this->platform->isAnalytics();
    }

    /** Odkaz do správce reklam nebo analytiky, ať se z upozornění dá rovnou prokliknout. */
    public function managerUrl(): ?string
    {
        return match ($this->platform) {
            AdPlatform::Meta => 'https://adsmanager.facebook.com/adsmanager/manage/campaigns?act='.$this->external_id,
            AdPlatform::GoogleAds => 'https://ads.google.com/aw/campaigns?__e='.preg_replace('/\D/', '', $this->external_id),
            AdPlatform::Ga4 => 'https://analytics.google.com/analytics/web/#/p'.$this->external_id.'/reports/intelligenthome',
            AdPlatform::Demo, AdPlatform::DemoGa4 => null,
        };
    }

    /**
     * Odkud stáhnout historii nově propojeného účtu (config ads.backfill_months).
     * Stáhne ji až ranní `ads:sync`, nikdy hned po propojení.
     */
    public static function historyStart(): string
    {
        $months = min(Period::MAX_HISTORY_MONTHS, max(1, (int) config('ads.backfill_months')));

        // Den rezervy, ať začátek přesně na hraně 37 měsíců Meta neodmítne.
        return now()->subMonthsNoOverflow($months)->addDay()->toDateString();
    }

    /**
     * Zapíše, do kterého dne máme čísla kompletní. Posune se jen stažením,
     * které navazuje na to, co už máme (ranní běh, ruční obnova). Doplňování
     * starší historie nebo jednorázové stažení kusu uprostřed konec nemění.
     */
    public function markDataThrough(Period $period): void
    {
        $through = $this->data_through;

        if ($through !== null && $period->from->gt($through->copy()->addDay())) {
            return;
        }

        if ($through === null || $period->to->gt($through)) {
            $this->data_through = $period->to->toDateString();
        }
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            null, 'active' => 'Aktivní',
            'disabled' => 'Zablokovaný',
            'unsettled' => 'Nezaplacený',
            'pending_risk_review' => 'Kontrola Mety',
            'pending_settlement' => 'Čeká na platbu',
            'grace_period' => 'Lhůta na zaplacení',
            'pending_closure' => 'Ruší se',
            'closed' => 'Zrušený',
            default => $this->status,
        };
    }

    protected function casts(): array
    {
        return [
            'platform' => AdPlatform::class,
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
            'issues' => 'array',
            'issues_checked_at' => 'datetime',
            'backfill_from' => 'date',
            'data_through' => 'date',
            'refresh_requested_at' => 'datetime',
            'refreshed_at' => 'datetime',
        ];
    }
}
