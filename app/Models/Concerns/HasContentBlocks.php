<?php

namespace App\Models\Concerns;

use App\Support\ResponsiveImage;
use Illuminate\Support\Collection;

/**
 * Skládaný obsah z Filament Builderu. Model, který trait použije, musí mít
 * sloupec `blocks` (json) a v `$casts` ho přetypovaný na `array`.
 *
 * Editaci řeší App\Filament\Schemas\ContentBlocks, vykreslení komponenty
 * v resources/views/components/blocks/.
 */
trait HasContentBlocks
{
    /** Pole bloků, ve kterých je uložená cesta k obrázku na disku `public`. */
    private const BLOCK_IMAGE_KEYS = ['image', 'before', 'after'];

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
     * Obrázky se v blocích ukládají jako cesta na disku `public`; šablona
     * potřebuje URL, zmenšeniny a rozměry. Ke každé cestě proto přibude klíč
     * se sufixem `_image` s hotovým polem pro <x-media>, případně `null`,
     * když soubor chybí — blok se pak vysází bez obrázku.
     *
     * Popisek (alt) leží v sousedním klíči `*_alt`, ať ho nemusí dohledávat
     * šablona.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolveBlockImages(array $data): array
    {
        foreach (self::BLOCK_IMAGE_KEYS as $key) {
            $data[$key.'_image'] = filled($data[$key] ?? null)
                ? ResponsiveImage::make($data[$key], (string) ($data[$key.'_alt'] ?? ''))
                : null;
        }

        return $data;
    }
}
