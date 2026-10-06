<?php

namespace App\Models;

use App\Models\Concerns\HasReviews;
use App\Models\Concerns\TracksClientViews;
use App\Support\AuditMarkdown;
use App\Support\UniqueSlug;
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
    use HasReviews, TracksClientViews;

    /**
     * Řádek, od kterého je text v omezeném režimu zamčený. Klient vidí,
     * co je nad ním, a z kapitol pod ním jen nadpisy s výzvou k hovoru.
     */
    public const LOCK_MARKER = '::: zámek';

    private const LOCK_PATTERN = '/^:::[ \t]*zámek[ \t]*$/mu';

    protected $guarded = [];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true)->whereNotNull('public_token');
    }

    /**
     * Najde audit podle adresy. Odkazy rozeslané před zavedením slugů
     * nesou náhodný token, ty musí fungovat dál.
     */
    public function scopeSharedAs(Builder $query, string $key): Builder
    {
        return $query->where(fn (Builder $query) => $query->where('slug', $key)->orWhere('public_token', $key));
    }

    /** Odkaz pro klienta. Null, dokud sdílení nezapneme. */
    public function publicUrl(): ?string
    {
        if (! $this->is_public || ! $this->public_token) {
            return null;
        }

        return $this->previewUrl();
    }

    /** Náhled pro přihlášeného správce. Funguje i u auditu, který ještě nesdílíme. */
    public function previewUrl(): ?string
    {
        $key = $this->slug ?: $this->public_token;

        return $key ? route('audit.show', $key) : null;
    }

    /**
     * Text převedený do HTML a obsah pro boční navigaci.
     *
     * V omezeném režimu jen část nad značkou zámku. Kapitoly pod ní
     * se vrátí jako `locked`, stránka z nich ukáže jen nadpisy.
     * Bez omezeného režimu značka z textu zmizí a klient vidí všechno.
     *
     * @return array{html: string, toc: list<array{id: string, title: string}>, locked: list<string>}
     */
    public function rendered(): array
    {
        $parts = preg_split(self::LOCK_PATTERN, (string) $this->body, 2);

        if (! $this->is_teaser || count($parts) < 2) {
            return AuditMarkdown::render(implode("\n", $parts)) + ['locked' => []];
        }

        return AuditMarkdown::render($parts[0]) + [
            'locked' => array_column(AuditMarkdown::render($parts[1])['toc'], 'title'),
        ];
    }

    /** Claude audit právě přepisuje. Po čtvrt hodině se to bere jako zaseknuté. */
    public function isBeingWritten(): bool
    {
        return $this->ai_status === 'running' && $this->updated_at?->gt(now()->subMinutes(15));
    }

    public function hasLockMarker(): bool
    {
        return (bool) preg_match(self::LOCK_PATTERN, (string) $this->body);
    }

    protected function viewActivitySubject(bool $first): string
    {
        return $first ? 'Otevřeli audit poprvé' : 'Znovu otevřeli audit';
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

            // Čitelná adresa podle klienta: /audit/svet-cejlonu.
            if (! $audit->slug && UniqueSlug::supported($audit)) {
                $audit->slug = UniqueSlug::for($audit, $audit->client?->name ?? $audit->title, 'audit');
            }
        });

        // Checklist z auditu vzniká skrytý, v omezeném režimu by prozradil
        // postup oprav. Jakmile klient dostane plnou verzi, dostane i jej.
        static::saved(function (self $audit): void {
            if ($audit->is_public && ! $audit->is_teaser && $audit->wasChanged('is_teaser') && $audit->client) {
                $audit->client->checklists()->forClients()->where('is_public', false)->update(['is_public' => true]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'audited_at' => 'date',
            'highlights' => 'array',
            'is_public' => 'boolean',
            'is_teaser' => 'boolean',
            'first_viewed_at' => 'datetime',
            'last_viewed_at' => 'datetime',
        ];
    }
}
