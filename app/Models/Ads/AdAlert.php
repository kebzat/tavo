<?php

namespace App\Models\Ads;

use App\Enums\Ads\AlertSeverity;
use App\Enums\Ads\AlertStatus;
use App\Models\Client;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Upozornění z denní kontroly s konkrétním doporučením, co udělat. */
class AdAlert extends Model
{
    protected $guarded = [];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AdAccount::class, 'ad_account_id');
    }

    /** Otevřená a ta, která se řeší. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->whereIn('status', AlertStatus::active());
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', AlertStatus::Open);
    }

    public function scopeWorstFirst(Builder $query): Builder
    {
        return $query
            ->orderByRaw("case severity when 'critical' then 0 when 'warning' then 1 else 2 end")
            ->latest('last_seen_on');
    }

    protected function casts(): array
    {
        return [
            'severity' => AlertSeverity::class,
            'status' => AlertStatus::class,
            'snapshot' => 'array',
            'detected_on' => 'date',
            'last_seen_on' => 'date',
            'resolved_at' => 'datetime',
        ];
    }
}
