<?php

namespace App\Models;

use App\Models\Concerns\TracksClientViews;
use App\Support\ResponsiveImage;
use App\Support\UniqueSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Potenciální spolupráce: dopadová stránka pro firmu, kterou chceme získat.
 * Sdílí se odkazem /potencialni-spoluprace/{slug}, spravuje se v nástrojích.
 *
 * Sekce jsou JSON pole z repeaterů. Metody níž z nich vyhází prázdné řádky
 * (zůstanou po odebrání hodnoty v administraci) a připraví, co šablona
 * potřebuje, ať v Blade nic nedopočítáváme.
 */
class Proposal extends Model
{
    use TracksClientViews;

    /** Kam na stránce ukázka patří. Mezi sekce, ne na konec. */
    public const EXAMPLE_PLACEMENTS = [
        'after_findings' => 'Za „Co jsme objevili“',
        'after_recommendations' => 'Za „Co doporučujeme“',
        'after_steps' => 'Za „Akční kroky“',
    ];

    /** Štítek nálezu: popisek a třída z .audit-tag v app.css. */
    public const FINDING_TONES = [
        'problem' => ['label' => 'Problém', 'class' => 'audit-tag--bad'],
        'opportunity' => ['label' => 'Příležitost', 'class' => 'audit-tag--warn'],
        'strength' => ['label' => 'Silná stránka', 'class' => 'audit-tag--good'],
    ];

    protected $guarded = [];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /** Odkaz pro klienta. Null, dokud sdílení nezapneme. */
    public function publicUrl(): ?string
    {
        return $this->is_public ? $this->previewUrl() : null;
    }

    /** Náhled pro přihlášeného správce. Funguje i u stránky, kterou ještě nesdílíme. */
    public function previewUrl(): ?string
    {
        return $this->slug ? route('proposal.show', $this->slug) : null;
    }

    protected function viewActivitySubject(bool $first): string
    {
        return $first ? 'Otevřeli nabídku spolupráce poprvé' : 'Znovu otevřeli nabídku spolupráce';
    }

    /** @return list<array{value: string, label: string}> */
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

    /**
     * Kdysi, dnes a s námi. Obrázek je vyřešený na zmenšeniny, `full_url`
     * vede na originál, ať se dá screenshot otevřít celý.
     *
     * @return list<array{label: string, title: string, body: string, image: ?array<string, mixed>, full_url: ?string}>
     */
    public function timelineItems(): array
    {
        return $this->rows('timeline')
            ->map(fn (array $row): array => [
                'label' => (string) ($row['label'] ?? ''),
                'title' => (string) $row['title'],
                'body' => (string) ($row['body'] ?? ''),
                ...$this->image($row),
            ])
            ->all();
    }

    /** @return list<array{title: string, body: string, tag: ?array{label: string, class: string}}> */
    public function findingItems(): array
    {
        return $this->rows('findings')
            ->map(fn (array $row): array => [
                'title' => (string) $row['title'],
                'body' => (string) ($row['body'] ?? ''),
                'tag' => self::FINDING_TONES[$row['tone'] ?? ''] ?? null,
            ])
            ->all();
    }

    /** @return list<array{title: string, body: string, who: string}> */
    public function recommendationItems(): array
    {
        return $this->rows('recommendations')
            ->map(fn (array $row): array => [
                'title' => (string) $row['title'],
                'body' => (string) ($row['body'] ?? ''),
                'who' => (string) ($row['who'] ?? ''),
            ])
            ->all();
    }

    /**
     * Kroky rozdělené na to, co děláme hned, a co přijde postupně.
     *
     * @return array{now: list<array{when: string, title: string, body: string}>, later: list<array{when: string, title: string, body: string}>}
     */
    public function stepGroups(): array
    {
        $steps = $this->rows('steps')->map(fn (array $row): array => [
            'when' => (string) ($row['when'] ?? ''),
            'title' => (string) $row['title'],
            'body' => (string) ($row['body'] ?? ''),
            'later' => (bool) ($row['later'] ?? false),
        ]);

        $strip = fn (array $step): array => array_diff_key($step, ['later' => true]);

        return [
            'now' => $steps->reject(fn (array $step): bool => $step['later'])->map($strip)->values()->all(),
            'later' => $steps->filter(fn (array $step): bool => $step['later'])->map($strip)->values()->all(),
        ];
    }

    /**
     * Ukázky podle místa na stránce. Obrázek je vyřešený na zmenšeniny,
     * `full_url` vede na originál (dlouhý návrh webu se dá otevřít celý).
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function examplesByPlacement(): array
    {
        $grouped = array_fill_keys(array_keys(self::EXAMPLE_PLACEMENTS), []);

        foreach ($this->rows('examples') as $row) {
            $placement = array_key_exists($row['placement'] ?? '', $grouped) ? $row['placement'] : 'after_findings';

            $grouped[$placement][] = [
                'kind' => (string) ($row['kind'] ?? ''),
                'title' => (string) $row['title'],
                'body' => (string) ($row['body'] ?? ''),
                ...$this->image($row),
                'scroll' => (bool) ($row['scroll'] ?? false),
                'link_url' => filled($row['link_url'] ?? null) ? (string) $row['link_url'] : null,
                'link_label' => (string) (($row['link_label'] ?? null) ?: 'Otevřít ukázku'),
            ];
        }

        return $grouped;
    }

    /** @return list<array{title: string, body: string}> */
    public function principleItems(): array
    {
        return $this->rows('principles')
            ->map(fn (array $row): array => [
                'title' => (string) $row['title'],
                'body' => (string) ($row['body'] ?? ''),
            ])
            ->all();
    }

    /**
     * Obrázek z řádku repeateru: zmenšeniny pro <img> a odkaz na originál.
     *
     * @param  array<string, mixed>  $row
     * @return array{image: ?array<string, mixed>, full_url: ?string}
     */
    private function image(array $row): array
    {
        $path = $row['image'] ?? null;

        if (blank($path)) {
            return ['image' => null, 'full_url' => null];
        }

        $image = ResponsiveImage::make($path, (string) ($row['image_alt'] ?? ''));

        return ['image' => $image, 'full_url' => $image ? Storage::disk('public')->url($path) : null];
    }

    /** Řádky repeateru bez těch, kterým chybí nadpis. */
    private function rows(string $attribute): Collection
    {
        return collect($this->{$attribute} ?? [])
            ->filter(fn ($row): bool => is_array($row) && filled($row['title'] ?? null))
            ->values();
    }

    protected static function booted(): void
    {
        static::saving(function (self $proposal): void {
            if (! $proposal->slug) {
                $proposal->slug = UniqueSlug::for($proposal, $proposal->company_name);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'prepared_at' => 'date',
            'highlights' => 'array',
            'timeline' => 'array',
            'findings' => 'array',
            'recommendations' => 'array',
            'steps' => 'array',
            'examples' => 'array',
            'principles' => 'array',
            'is_public' => 'boolean',
            'first_viewed_at' => 'datetime',
            'last_viewed_at' => 'datetime',
        ];
    }
}
