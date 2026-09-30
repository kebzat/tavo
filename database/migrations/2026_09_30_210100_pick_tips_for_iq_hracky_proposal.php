<?php

use App\Models\Proposal;
use App\Support\ResponsiveImage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * IQ Hračky: tipy „Jak to vypadá, když je to zvládnuté“ vybrané pro obchod
 * s hračkami před Vánoci, seřazené podle cesty zákazníka (lišta → detail
 * produktu → košík). Přibyla horní lišta a video v galerii (Venira.cz)
 * a propracovaný košík (Rybizak.cz). Doplňkové produkty z Alzy a zbylé tipy
 * se přesunuly na /pro-klienty, na kterou ukázka odkazuje.
 *
 * Přepíše jen ukázku, která pořád obsahuje přesně ty čtyři tipy z migrace
 * 160000. Když ji správce mezitím upravil, nechá ji být.
 *
 * Pravidla pro psaní textů: .claude/skills/tavo-copy/SKILL.md
 */
return new class extends Migration
{
    private const TITLE = 'Jak to vypadá, když je to zvládnuté';

    private const PREVIOUS_ITEMS = [
        'Košík: Pompo.cz',
        'Stavová lišta: Sparkys.cz',
        'Doplňkové produkty: Alza.cz',
        'Popis produktu: běžný a prémiový (Alza.cz)',
    ];

    public function up(): void
    {
        $proposal = Proposal::where('slug', 'iq-hracky')->first();

        if (! $proposal) {
            return;
        }

        $examples = $proposal->examples ?? [];
        $index = collect($examples)->search(fn ($example): bool => ($example['title'] ?? null) === self::TITLE
            && array_column($example['items'] ?? [], 'title') === self::PREVIOUS_ITEMS);

        if ($index === false) {
            return;
        }

        $previous = collect($examples[$index]['items'])->keyBy('title');

        foreach ($this->newImages() as $path) {
            $this->copyImage($path);
        }

        $examples[$index] = [
            ...$examples[$index],
            'body' => 'Pár příkladů z e-shopů, které to dělají dobře. Vybrali jsme to, co u vás dává smysl před Vánoci, '
                .'v pořadí, jak jimi prochází zákazník: od lišty nahoře přes detail produktu po košík. '
                .'Screenshoty jsou z 30. 9. 2026, po kliknutí se otevřou celé.',
            'link_url' => route('pages.show', 'pro-klienty'),
            'link_label' => 'Další tipy pro e-shopy',
            'items' => [
                $this->item(
                    'Horní lišta: Venira.cz',
                    'Tenký pruh nad hlavičkou s tím, co právě platí: akce s odpočtem, doprava zdarma. U vás by tam před Vánoci '
                        .'patřil poslední termín, kdy objednávka dorazí pod stromeček, a 5 z 5 na Heurece.',
                    'spoluprace/ukazka-horni-lista-venira.jpg',
                    'Horní lišta s odpočtem akce nad hlavičkou e-shopu Venira.cz',
                ),
                $this->item(
                    'Video v galerii produktu: Venira.cz',
                    'Mezi fotkami je hned první video. Ukáže, jak produkt vypadá v ruce a při použití, líp než pět fotek. '
                        .'Krátká videa s dětmi od tvůrkyně by se sem hodila přesně.',
                    'spoluprace/ukazka-video-produktu-venira.jpg',
                    'Galerie produktu s videem jako první náhled na Venira.cz',
                ),
                $previous['Popis produktu: běžný a prémiový (Alza.cz)'],
                $previous['Stavová lišta: Sparkys.cz'],
                $previous['Košík: Pompo.cz'],
                $this->item(
                    'Košík, který prodává: Rybizak.cz',
                    'Vedle obsahu košíku ukazuje, kolik zbývá do dopravy zdarma a do dárku, nabídne přednostní expedici '
                        .'a dole připomene, na co zákazník mohl zapomenout. U vánočních nákupů hlavně ta lišta do dopravy zdarma.',
                    'spoluprace/ukazka-kosik-rybizak.jpg',
                    'Košík na Rybizak.cz s ukazatelem dopravy zdarma, dárkem a doporučenými produkty',
                ),
            ],
        ];

        $proposal->update(['examples' => $examples]);
    }

    public function down(): void
    {
        // Původní výběr vrátit nejde bez zálohy, správce ho případně upraví v nástrojích.
    }

    /** @return list<string> */
    private function newImages(): array
    {
        return [
            'spoluprace/ukazka-horni-lista-venira.jpg',
            'spoluprace/ukazka-video-produktu-venira.jpg',
            'spoluprace/ukazka-kosik-rybizak.jpg',
        ];
    }

    /** @return array{title: string, body: string, image: ?string, image_alt: string, video_url: null} */
    private function item(string $title, string $body, string $image, string $alt): array
    {
        return [
            'title' => $title,
            'body' => $body,
            'image' => Storage::disk('public')->exists($image) ? $image : null,
            'image_alt' => $alt,
            'video_url' => null,
        ];
    }

    /** Obrázky se do gitu nedostanou přes storage/, leží v database/seeders/assets/. */
    private function copyImage(string $path): void
    {
        $source = database_path('seeders/assets/'.$path);
        $disk = Storage::disk('public');

        if (! File::isFile($source) || $disk->exists($path)) {
            return;
        }

        $disk->put($path, File::get($source));
        ResponsiveImage::generate($path);
    }
};
