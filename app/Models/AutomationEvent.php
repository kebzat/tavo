<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jedna zpráva, kterou automatizace nechala poslat: řádek CSV importu pro
 * Upgates (číslo objednávky → cílový stav). Ke každé objednávce a druhu jen
 * jednou (unikátní klíč). `pending` čeká, až si ji Upgates stáhne a stav
 * změní, `confirmed` = export objednávek už cílový stav ukazuje.
 */
class AutomationEvent extends Model
{
    public const KIND_REMINDER = 'reminder';

    public const KIND_LAST_DAY = 'last_day';

    public const KIND_RETURNED = 'returned';

    /** Od nejdůležitější. Důležitější zpráva nahradí méně důležitou, která ještě čeká. */
    public const KINDS = [self::KIND_RETURNED, self::KIND_LAST_DAY, self::KIND_REMINDER];

    public const STATE_PENDING = 'pending';

    public const STATE_CONFIRMED = 'confirmed';

    public const STATE_FAILED = 'failed';

    public const STATE_SKIPPED = 'skipped';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'stored_until' => 'date',
            'exported_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('state', self::STATE_PENDING);
    }

    public static function kindLabel(string $kind): string
    {
        return match ($kind) {
            self::KIND_REMINDER => 'Připomínka vyzvednutí (e-mail)',
            self::KIND_LAST_DAY => 'Poslední den k vyzvednutí (SMS)',
            self::KIND_RETURNED => 'Vrácená zásilka (e-mail)',
            default => $kind,
        };
    }

    public static function stateLabel(string $state): string
    {
        return match ($state) {
            self::STATE_PENDING => 'Čeká na Upgates',
            self::STATE_CONFIRMED => 'Potvrzeno',
            self::STATE_FAILED => 'Chyba',
            self::STATE_SKIPPED => 'Nahrazeno důležitější zprávou',
            default => $state,
        };
    }

    public static function stateColor(string $state): string
    {
        return match ($state) {
            self::STATE_PENDING => 'warning',
            self::STATE_CONFIRMED => 'success',
            self::STATE_FAILED => 'danger',
            default => 'gray',
        };
    }

    public function rank(): int
    {
        return (int) array_search($this->kind, self::KINDS, true);
    }
}
