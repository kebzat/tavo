<?php

namespace App\Models;

use App\Enums\ApiAbility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Přístup jednoho agenta ke strojovému rozhraní CRM. Ověřuje VerifyCrmToken.
 *
 * Samotný token se nikde neukládá, jen jeho otisk. Když se ztratí, založí
 * se nový a starý se zruší.
 */
class ApiToken extends Model
{
    /** Předpona, podle které se token pozná v .env nebo v logu. */
    public const PREFIX = 'tavo_';

    protected $guarded = [];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Založí token a vrátí ho i s hodnotou, kterou je potřeba ukázat
     * hned teď. Později už se z databáze zjistit nedá.
     *
     * @param  list<ApiAbility>  $abilities
     * @return array{0: self, 1: string}
     */
    public static function issue(string $name, array $abilities, ?User $createdBy = null): array
    {
        $plain = self::PREFIX.Str::random(48);

        $token = self::create([
            'name' => $name,
            'token_hash' => self::hash($plain),
            'abilities' => array_map(fn (ApiAbility $ability): string => $ability->value, $abilities),
            'created_by' => $createdBy?->getKey(),
        ]);

        return [$token, $plain];
    }

    public static function findActive(string $plain): ?self
    {
        if ($plain === '') {
            return null;
        }

        return self::query()->active()->where('token_hash', self::hash($plain))->first();
    }

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public function allows(ApiAbility $ability): bool
    {
        return in_array($ability->value, $this->abilities ?? [], true);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function revoke(): void
    {
        $this->forceFill(['revoked_at' => now()])->save();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function calls(): HasMany
    {
        return $this->hasMany(ApiTokenCall::class);
    }
}
