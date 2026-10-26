<?php

namespace App\Models\Ads;

use App\Enums\Ads\AdChangeType;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zásah v reklamách: změna rozpočtu, nová kreativa, jiná nabídka, úprava
 * webu. Z historie zásahů vychází graf (značky), report („Co jsme změnili“)
 * i agent, když komentuje výsledky. Bez zápisu by příčinu jen hádal.
 */
class AdChange extends Model
{
    protected $guarded = [];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(AdCampaignGroup::class, 'ad_campaign_group_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'ad_campaign_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Na co se zásah vztahoval: skupina, kampaň. Null = celý účet. */
    public function scopeLabel(): ?string
    {
        return collect([$this->group?->name, $this->campaign?->name])->filter()->implode(' · ') ?: null;
    }

    protected function casts(): array
    {
        return [
            'type' => AdChangeType::class,
            'changed_on' => 'date',
        ];
    }
}
