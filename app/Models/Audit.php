<?php

namespace App\Models;

use App\Support\AuditMarkdown;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Audit klientského webu. Sdílí se odkazem stejně jako checklist
 * a na sdílené stránce se s checklisty téhož klienta propojí.
 */
class Audit extends Model
{
    protected $guarded = [];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true)->whereNotNull('public_token');
    }

    /** Odkaz pro klienta. Null, dokud sdílení nezapneme. */
    public function publicUrl(): ?string
    {
        if (! $this->is_public || ! $this->public_token) {
            return null;
        }

        return route('audit.show', $this->public_token);
    }

    /**
     * Text převedený do HTML a obsah pro boční navigaci.
     *
     * @return array{html: string, toc: list<array{id: string, title: string}>}
     */
    public function rendered(): array
    {
        return AuditMarkdown::render((string) $this->body);
    }

    /**
     * Dlaždice s čísly bez prázdných řádků, které v administraci zůstanou
     * po odebrání hodnoty.
     *
     * @return list<array{value: string, label: string}>
     */
    public function highlightTiles(): array
    {
        return collect($this->highlights ?? [])
            ->filter(fn ($tile): bool => filled($tile['value'] ?? null))
            ->map(fn (array $tile): array => [
                'value' => (string) $tile['value'],
                'label' => (string) ($tile['label'] ?? ''),
            ])
            ->values()
            ->all();
    }

    protected static function booted(): void
    {
        // Token přidělíme hned při založení, ať je odkaz připravený dřív,
        // než ho někdo zapne. Stejně jako u checklistu.
        static::saving(function (self $audit): void {
            if (! $audit->public_token) {
                $audit->public_token = Str::random(40);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'audited_at' => 'date',
            'highlights' => 'array',
            'is_public' => 'boolean',
        ];
    }
}
