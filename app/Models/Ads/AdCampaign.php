<?php

namespace App\Models\Ads;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdCampaign extends Model
{
    protected $guarded = [];

    /** Stavy kampaně z Mety, které znamenají, že teď běží. */
    public const RUNNING_STATUSES = ['ACTIVE', 'IN_PROCESS', 'WITH_ISSUES'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(AdAccount::class, 'ad_account_id');
    }

    /** Skupina kampaní, null = nezařazená. */
    public function group(): BelongsTo
    {
        return $this->belongsTo(AdCampaignGroup::class, 'ad_campaign_group_id');
    }

    public function stats(): HasMany
    {
        return $this->hasMany(AdDailyStat::class);
    }

    /** Stav lidsky: „běží“, „vypnutá“. Null, dokud ranní stažení stav nezjistilo. */
    public function statusLabel(): ?string
    {
        return self::label($this->status);
    }

    public static function label(?string $status): ?string
    {
        return match ($status) {
            null, '' => null,
            'ACTIVE' => 'běží',
            'IN_PROCESS' => 'spouští se',
            'WITH_ISSUES' => 'běží s potížemi',
            'PAUSED', 'CAMPAIGN_PAUSED' => 'vypnutá',
            'ARCHIVED' => 'archivovaná',
            'DELETED' => 'smazaná',
            default => mb_strtolower($status),
        };
    }
}
