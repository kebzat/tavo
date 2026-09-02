<?php

namespace App\Models\Crm;

use App\Enums\Crm\TemplateChannel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Připravený text k odeslání. Placeholdery se dosazují až při použití,
 * viz App\Support\Crm\TemplateRenderer.
 */
class MessageTemplate extends Model
{
    protected $table = 'crm_message_templates';

    protected $guarded = [];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected function casts(): array
    {
        return [
            'channel' => TemplateChannel::class,
            'is_active' => 'boolean',
        ];
    }
}
