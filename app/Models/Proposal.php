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
        'after_principles' => 'Na konec, před výzvu',
    ];

    /** Štítek nálezu: popisek a třída z .audit-tag v app.css. */
    public const FINDING_TONES = [
        'problem' => ['label' => 'Problém', 'class' => 'audit-tag--bad'],
        'opportunity' => ['label' => 'Příležitost', 'class' => 'audit-tag--warn'],
        'strength' => ['label' => 'Silná stránka', 'class' => 'audit-tag--good'],
    ];

    /**
     * Naléhavost nálezu. Nálezy se podle ní na stránce seskupí pod nadpisy,
     * v tomhle pořadí. Bez vyplněné naléhavosti zůstane jeden souvislý seznam.
     */
    public const FINDING_PRIORITIES = [
        'urgent' => 'Urgentní',
        'important' => 'Důležité',
        'later' => 'Až bude čas',
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

    /**
     * Nálezy seskupené podle naléhavosti. Nálezy bez ní jdou na začátek bez
     * nadpisu, takže stránka bez vyplněné naléhavosti vypadá jako dřív.
     *
     * @return list<array{label: ?string, items: list<array{title: string, body: string, tag: ?array{label: string, class: string}}>}>
     */
    public function findingGroups(): array
    {
        $rows = $this->rows('findings');
        $items = $this->findingItems();

        return collect([null, ...array_keys(self::FINDING_PRIORITIES)])
            ->map(fn (?string $priority): array => [
                'label' => $priority ? self::FINDING_PRIORITIES[$priority] : null,
                'items' => $rows->keys()
                    ->filter(fn (int $i): bool => self::priorityOf($rows[$i]) === $priority)
                    ->map(fn (int $i): array => $items[$i])
                    ->values()
                    ->all(),
            ])
            ->filter(fn (array $group): bool => $group['items'] !== [])
            ->values()
            ->all();
    }

    /** @param  array<string, mixed>  $row */
    private static function priorityOf(array $row): ?string
    {
        $priority = $row['priority'] ?? null;

        return isset(self::FINDING_PRIORITIES[$priority]) ? $priority : null;
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
     * Varianty měsíční spolupráce. Co je v ceně, píše správce po řádcích,
     * prázdné řádky vypadnou.
     *
     * @return list<array{title: string, price: string, period: string, scope: string, body: string, features: list<string>, recommended: bool}>
     */
    public function packageItems(): array
    {
        return $this->rows('packages')
            ->map(fn (array $row): array => [
                'title' => (string) $row['title'],
                'price' => (string) ($row['price'] ?? ''),
                'period' => (string) ($row['period'] ?? ''),
                'scope' => (string) ($row['scope'] ?? ''),
                'body' => (string) ($row['body'] ?? ''),
                'features' => collect(preg_split('/\R/', (string) ($row['features'] ?? '')) ?: [])
                    ->map(fn (string $line): string => trim($line))
                    ->filter()
                    ->values()
                    ->all(),
                'recommended' => (bool) ($row['recommended'] ?? false),
            ])
            ->all();
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
                ...$this->exampleItems($row['items'] ?? null),
            ];
        }

        return $grouped;
    }

    /**
     * Mřížka ukázek pod textem: screenshoty e-shopů, videa z Google Disku.
     * Položka bez obrázku i bez videa nemá co ukázat, vypadne. `items_layout`
     * říká šabloně, jestli jde o samá videa na výšku, nebo o screenshoty.
     *
     * @return array{items: list<array<string, mixed>>, items_layout: string}
     */
    private function exampleItems(mixed $rows): array
    {
        $items = collect(is_array($rows) ? $rows : [])
            ->filter(fn ($row): bool => is_array($row))
            ->map(fn (array $row): array => [
                'title' => (string) ($row['title'] ?? ''),
                'body' => (string) ($row['body'] ?? ''),
                ...$this->image($row),
                'video' => self::driveVideo($row['video_url'] ?? null),
            ])
            ->filter(fn (array $item): bool => $item['image'] !== null || $item['video'] !== null)
            ->values();

        $onlyVideos = $items->isNotEmpty() && $items->every(fn (array $item): bool => $item['video'] !== null);

        return ['items' => $items->all(), 'items_layout' => $onlyVideos ? 'video' : 'image'];
    }

    /**
     * Video sdílené z Google Disku. Přehrává se v Diskovém přehrávači, takže
     * cizí videa na web nekopírujeme. Náhled bere Disk, dokud správce nenahraje
     * vlastní obrázek. Soubor musí být sdílený „kdokoli s odkazem“.
     *
     * @return array{embed_url: string, poster_url: string}|null
     */
    private static function driveVideo(mixed $url): ?array
    {
        if (! is_string($url) || ! str_contains($url, 'drive.google.com')) {
            return null;
        }

        if (! preg_match('~/file/d/([\w-]{10,})|[?&]id=([\w-]{10,})~', $url, $match)) {
            return null;
        }

        $id = ($match[1] ?? '') ?: $match[2];

        return [
            'embed_url' => "https://drive.google.com/file/d/{$id}/preview",
            'poster_url' => "https://drive.google.com/thumbnail?id={$id}&sz=w720",
        ];
    }

    /**
     * Zkušenosti z praxe. Věta zůstává, jak ji správce napsal, jen se rozdělí
     * na kousky, aby šablona mohla vyznačená místa (čísla) zvýraznit.
     *
     * @return list<list<array{text: string, strong: bool, nowrap: bool}>>
     */
    public function experienceItems(): array
    {
        return collect($this->experiences ?? [])
            ->filter(fn ($row): bool => is_array($row) && filled($row['text'] ?? null))
            ->map(fn (array $row): array => self::emphasize((string) $row['text'], (array) ($row['emphasis'] ?? [])))
            ->values()
            ->all();
    }

    /**
     * Rozdělí text na obyčejné a zvýrazněné kousky. Zvýrazní se každý výskyt
     * zadaných frází, delší fráze mají přednost před kratšími.
     *
     * @param  array<int, mixed>  $phrases
     * @return list<array{text: string, strong: bool, nowrap: bool}>
     */
    private static function emphasize(string $text, array $phrases): array
    {
        $phrases = collect($phrases)
            ->filter(fn ($phrase): bool => is_string($phrase) && trim($phrase) !== '')
            ->map(fn (string $phrase): string => trim($phrase))
            ->sortByDesc(fn (string $phrase): int => mb_strlen($phrase))
            ->map(fn (string $phrase): string => preg_quote($phrase, '~'));

        if ($phrases->isEmpty()) {
            return [['text' => $text, 'strong' => false, 'nowrap' => false]];
        }

        $parts = preg_split('~('.$phrases->implode('|').')~u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];

        return collect($parts)
            ->map(fn (string $part, int $index): array => [
                'text' => $part,
                'strong' => $index % 2 === 1,
                // Krátké číslo („3–10 Kč“) se nemá lámat na pomlčce ani mezeře.
                'nowrap' => $index % 2 === 1 && mb_strlen($part) <= 16,
            ])
            ->reject(fn (array $part): bool => $part['text'] === '')
            ->values()
            ->all();
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
            'packages' => 'array',
            'examples' => 'array',
            'experiences' => 'array',
            'principles' => 'array',
            'is_public' => 'boolean',
            'first_viewed_at' => 'datetime',
            'last_viewed_at' => 'datetime',
        ];
    }
}
