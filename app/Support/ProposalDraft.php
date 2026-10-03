<?php

namespace App\Support;

use App\Models\Proposal;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Koncept nabídky spolupráce z JSON souboru v database/seeders/proposals/.
 * Soubor má stejná pole jako model Proposal. Obrázky leží v
 * database/seeders/assets/ a na veřejný disk se zkopírují při importu.
 *
 * Import je jednorázový: stránka se stejnou adresou se nepřepíše, další
 * úpravy patří do nástrojů. Koncept je vždy nesdílený.
 */
class ProposalDraft
{
    /** Zkratka v `link_url`, místo které se doplní odkaz na /pro-klienty. */
    public const TIPS_PAGE = 'pro-klienty';

    public static function directory(): string
    {
        return database_path('seeders/proposals');
    }

    /** @return list<string> */
    public static function files(): array
    {
        return collect(File::glob(self::directory().'/*.json'))->sort()->values()->all();
    }

    /** @return array<string, mixed> */
    public static function read(string $path): array
    {
        return json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
    }

    public static function import(string $path): ?Proposal
    {
        $data = self::read($path);

        if (Proposal::where('slug', $data['slug'])->exists()) {
            return null;
        }

        $data = self::withImages($data);
        $data['examples'] = collect($data['examples'] ?? [])
            ->map(fn (array $example): array => [
                ...$example,
                'link_url' => ($example['link_url'] ?? null) === self::TIPS_PAGE
                    ? route('pages.show', self::TIPS_PAGE)
                    : ($example['link_url'] ?? null),
            ])
            ->all();

        // Zkušenosti jsou společné, berou se z první nabídky, pokud je soubor nemá.
        $data['experiences'] ??= Proposal::where('slug', 'iq-hracky')->value('experiences') ?? [];

        // Starší migrace importují všechny soubory. Na čisté databázi tak
        // narazí i na koncept s poli, pro která sloupec přibude až později.
        $data = Arr::only($data, Schema::getColumnListing('proposals'));

        return Proposal::create([...Arr::except($data, ['id', 'client_id']), 'is_public' => false]);
    }

    /**
     * Zkopíruje obrázky na veřejný disk. Obrázek, který v podkladech chybí,
     * se vynechá, ať stránka neukazuje rozbitý náhled.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function withImages(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($key === 'image' && is_string($value)) {
                $data[$key] = self::copyImage($value);
            } elseif (is_array($value)) {
                $data[$key] = self::withImages($value);
            }
        }

        return $data;
    }

    private static function copyImage(string $path): ?string
    {
        $disk = Storage::disk('public');

        if ($disk->exists($path)) {
            return $path;
        }

        $source = database_path('seeders/assets/'.$path);

        if (! File::isFile($source)) {
            return null;
        }

        $disk->put($path, File::get($source));
        ResponsiveImage::generate($path);

        return $path;
    }
}
