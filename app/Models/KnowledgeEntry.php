<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Poznatek v mozku TAVEO: jedna zkušenost, pravidlo, postup nebo fakt
 * o klientovi. Od agenta přijde jako návrh, platí až po schválení
 * člověkem. Návrh s `replaces_id` je nové znění staršího poznatku:
 * schválením starý odejde do archivu. Viz docs/MOZEK.md.
 */
class KnowledgeEntry extends Model
{
    protected $guarded = [];

    public const LAYER_COMPANY = 'firma';

    public const LAYER_CLIENT = 'klient';

    public const LAYER_PROCEDURE = 'postup';

    public const STATUS_PROPOSED = 'proposed';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_ARCHIVED = 'archived';

    /** @return array<string, string> */
    public static function layers(): array
    {
        return [
            self::LAYER_COMPANY => 'Firma',
            self::LAYER_CLIENT => 'Klient',
            self::LAYER_PROCEDURE => 'Postup',
        ];
    }

    /** @return array<string, string> */
    public static function statuses(): array
    {
        return [
            self::STATUS_PROPOSED => 'Ke schválení',
            self::STATUS_APPROVED => 'Platí',
            self::STATUS_REJECTED => 'Zamítnutý',
            self::STATUS_ARCHIVED => 'Archiv',
        ];
    }

    /** @return array<string, string> odkud poznatek přišel */
    public static function originTypes(): array
    {
        return [
            'rucne' => 'Ručně',
            'podklad' => 'Z podkladů',
            'clickup' => 'Z úlohy v ClickUpu',
            'grafika' => 'Z reklamní grafiky',
            'dotaz' => 'Z rozhovoru s mozkem',
            'agent' => 'Od agenta',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Skill, ke kterému poznatek / dokument patří (know-how skillu, zkušenost u klienta v jeho oboru). */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class, 'knowledge_source_id');
    }

    public function agentJob(): BelongsTo
    {
        return $this->belongsTo(AgentJob::class);
    }

    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_id');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeProposed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PROPOSED);
    }

    public function isProposed(): bool
    {
        return $this->status === self::STATUS_PROPOSED;
    }

    /** Schválit. Nové znění staršího poznatku ho nahradí (starý do archivu). */
    public function approve(string $by): void
    {
        DB::transaction(function () use ($by): void {
            $this->update(['status' => self::STATUS_APPROVED, 'reviewed_by' => $by, 'reviewed_at' => now()]);

            if ($this->replaces?->status === self::STATUS_APPROVED) {
                // Starý poznatek se nahrazuje celý, i se „vždy v hlavě“.
                $this->update(['always' => $this->always || $this->replaces->always]);
                $this->replaces->update(['status' => self::STATUS_ARCHIVED, 'reviewed_by' => $by, 'reviewed_at' => now()]);
            }
        });
    }

    public function reject(string $by): void
    {
        $this->update(['status' => self::STATUS_REJECTED, 'reviewed_by' => $by, 'reviewed_at' => now()]);
    }

    public function layerLabel(): string
    {
        return self::layers()[$this->layer] ?? $this->layer;
    }

    public function originLabel(): string
    {
        return (self::originTypes()[$this->origin_type] ?? $this->origin_type).($this->origin ? ': '.$this->origin : '');
    }

    public function statusLabel(): string
    {
        return self::statuses()[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_PROPOSED => 'warning',
            self::STATUS_APPROVED => 'success',
            default => 'gray',
        };
    }

    /** Tvar pro agenta (API mozku i zadání úloh). */
    public function toAgent(): array
    {
        return array_filter([
            'id' => $this->getKey(),
            'vrstva' => $this->layer,
            'klient' => $this->client?->slug,
            'skill' => $this->skill?->slug,
            'nadpis' => $this->title,
            'text' => $this->body,
            'stitky' => $this->tags ?: null,
            'vzdy' => $this->always ?: null,
            'puvod' => $this->origin,
            'stav' => $this->status === self::STATUS_APPROVED ? null : $this->status,
            'nahrazuje' => $this->replaces_id,
            'zmeneno' => $this->updated_at?->toDateString(),
        ], fn ($value): bool => $value !== null);
    }

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'always' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }
}
