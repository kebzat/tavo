<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jedno volání strojového rozhraní CRM. Zapisuje VerifyCrmToken, maže
 * `model:prune` po 90 dnech (routes/console.php).
 */
class ApiTokenCall extends Model
{
    use MassPrunable;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'called_at' => 'datetime',
        ];
    }

    public function prunable(): Builder
    {
        return static::where('called_at', '<', now()->subDays(90));
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(ApiToken::class, 'api_token_id');
    }
}
