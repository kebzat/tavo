<?php

namespace App\Models\Concerns;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

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
