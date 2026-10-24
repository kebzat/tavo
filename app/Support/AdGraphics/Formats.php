<?php

namespace App\Support\AdGraphics;

/**
 * Formáty reklamní grafiky na výběr. Rozměry v pixelech podle doporučení
 * Mety (příspěvek 4 : 5, Story 9 : 16, odkaz 1,91 : 1) a běžných bannerů.
 */
class Formats
{
    /** @var array<string, array{label: string, width: int, height: int}> */
    public const PRESETS = [
        'ig_post' => ['label' => 'Příspěvek IG / FB na výšku', 'width' => 1080, 'height' => 1350],
        'square' => ['label' => 'Příspěvek čtverec', 'width' => 1080, 'height' => 1080],
        'story' => ['label' => 'Story / Reels', 'width' => 1080, 'height' => 1920],
        'fb_link' => ['label' => 'FB odkaz / banner', 'width' => 1200, 'height' => 628],
        'web_banner' => ['label' => 'Banner na web', 'width' => 1920, 'height' => 600],
        'display' => ['label' => 'Bannerová reklama (obsahová síť)', 'width' => 300, 'height' => 250],
    ];

    /** @return array<string, string> klíč => popisek s rozměrem pro výběr */
    public static function options(): array
    {
        return array_map(fn (array $format): string => $format['label'].' · '.$format['width'].'×'.$format['height'], self::PRESETS);
    }

    /**
     * Vybrané předvolby a vlastní rozměry v jednom seznamu.
     *
     * @param  list<string>  $presets
     * @param  list<array{label?: ?string, width?: mixed, height?: mixed}>  $custom
     * @return list<array{label: string, width: int, height: int}>
     */
    public static function resolve(array $presets, array $custom): array
    {
        $formats = collect($presets)
            ->filter(fn (string $key): bool => isset(self::PRESETS[$key]))
            ->map(fn (string $key): array => self::PRESETS[$key])
            ->values();

        foreach ($custom as $format) {
            $width = (int) ($format['width'] ?? 0);
            $height = (int) ($format['height'] ?? 0);

            if ($width > 0 && $height > 0) {
                $formats->push(['label' => trim((string) ($format['label'] ?? '')) ?: 'Vlastní rozměr', 'width' => $width, 'height' => $height]);
            }
        }

        return $formats->unique(fn (array $format): string => $format['width'].'x'.$format['height'])->values()->all();
    }

    /** @param  array{label: string, width: int, height: int}  $format */
    public static function label(array $format): string
    {
        return $format['label'].' '.$format['width'].'×'.$format['height'];
    }

    /**
     * Poměr stran pro Gemini (podporuje jen pevnou sadu), nejbližší k rozměru.
     */
    public static function aspectRatio(int $width, int $height): string
    {
        $ratios = ['1:1' => 1, '4:5' => 0.8, '3:4' => 0.75, '2:3' => 2 / 3, '9:16' => 9 / 16, '5:4' => 1.25, '4:3' => 4 / 3, '3:2' => 1.5, '16:9' => 16 / 9, '21:9' => 21 / 9];
        $target = $width / max(1, $height);

        return collect($ratios)->sortBy(fn (float $ratio): float => abs(log($ratio / $target)))->keys()->first();
    }
}
