<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class Page extends Model
{
    /** Pole bloků, ve kterých je uložená cesta k obrázku na disku `public`. */
    private const BLOCK_IMAGE_KEYS = ['image', 'before', 'after'];

    protected $guarded = [];

    protected $casts = [
        'published' => 'boolean',
        'hero_cta' => 'boolean',
        'blocks' => 'array',
    ];

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('published', true);
    }

    /**
     * Nadpis rozsekaný na části kolem zvýrazněného výrazu, aby ho šablona mohla
     * vysázet cihlovou kurzívou. Když `hero_accent` v nadpisu není, vrátí se celý
     * nadpis v jednom kuse a zvýraznění se prostě nekoná.
     *
     * @return array{before: string, accent: ?string, after: string}
     */
    public function headlineParts(): array
    {
        $title = (string) $this->title;
        $accent = trim((string) $this->hero_accent);

        if ($accent === '' || ! str_contains($title, $accent)) {
            return ['before' => $title, 'accent' => null, 'after' => ''];
        }

        $at = mb_strpos($title, $accent);

        return [
            'before' => mb_substr($title, 0, $at),
            'accent' => $accent,
            'after' => mb_substr($title, $at + mb_strlen($accent)),
        ];
    }

    /**
     * Bloky připravené k vykreslení. Šablona pak jen vysází komponentu, kterou
     * dostane, a předá jí `data` — žádné dopočítávání v Blade.
     *
     * Jestli se blok vůbec zobrazí, si hlídá jeho komponenta sama: prázdný blok
     * nesmí na stránce nechat prázdné místo.
     *
     * @return Collection<int, array{component: string, data: array<string, mixed>}>
     */
    public function contentBlocks(): Collection
    {
        return collect($this->blocks ?? [])
            ->filter(fn (array $block): bool => filled($block['type'] ?? null))
            ->map(fn (array $block): array => [
                // Typ bloku je snake_case, soubor komponenty kebab-case (image_text → blocks.image-text).
                'component' => 'blocks.'.str_replace('_', '-', $block['type']),
                'data' => $this->resolveBlockImages($block['data'] ?? []),
            ])
            ->values();
    }

    /**
     * Obrázky se v blocích ukládají jako cesta na disku `public`; web potřebuje
     * URL. Ke každé cestě proto přibude klíč se sufixem `_url`.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolveBlockImages(array $data): array
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        foreach (self::BLOCK_IMAGE_KEYS as $key) {
            if (filled($data[$key] ?? null)) {
                $data[$key.'_url'] = $disk->url($data[$key]);
            }
        }

        return $data;
    }
}
