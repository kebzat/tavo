<?php

namespace App\Models;

use App\Enums\WorkArea;
use App\Support\Ads\Billing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Odpracovaný čas u klienta. Nad hodiny v paušálu se fakturuje sazbou
 * z ad_client_settings, viz App\Support\Ads\Billing.
 */
class TimeEntry extends Model
{
    protected $guarded = [];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ClientTask::class, 'task_id');
    }

    public function scopeInMonth(Builder $query, int $year, int $month): Builder
    {
        return $query->whereYear('worked_on', $year)->whereMonth('worked_on', $month);
    }

    public function hours(): float
    {
        return $this->minutes / 60;
    }

    /** „1:30 h“ */
    public function duration(): string
    {
        return Billing::formatMinutes($this->minutes);
    }

    protected function casts(): array
    {
        return [
            'worked_on' => 'date',
            'billable' => 'boolean',
            'invoiced_at' => 'datetime',
            'area' => WorkArea::class,
        ];
    }
}
