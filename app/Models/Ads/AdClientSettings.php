<?php

namespace App\Models\Ads;

use App\Enums\Ads\PrimaryGoal;
use App\Models\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Co jsme s klientem domluvili: cíl, rozpočet, cílová cena za konverzi,
 * paušál a komu posílat reporty. Paušál a sazba jsou jen pro nás.
 */
class AdClientSettings extends Model
{
    protected $guarded = [];

    protected $attributes = [
        'primary_goal' => 'purchases',
        'weekly_report' => true,
        'monthly_report' => true,
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    protected function casts(): array
    {
        return [
            'primary_goal' => PrimaryGoal::class,
            'monthly_budget' => 'float',
            'target_cpa' => 'float',
            'target_roas' => 'float',
            'included_hours' => 'float',
            'report_recipients' => 'array',
            'dashboard' => 'array',
            'weekly_report' => 'boolean',
            'monthly_report' => 'boolean',
            'advice_at' => 'datetime',
        ];
    }
}
