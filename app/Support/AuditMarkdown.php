<?php

namespace App\Support;

use Illuminate\Support\Str;
use League\CommonMark\GithubFlavoredMarkdownConverter;

/**
 * Převod textu auditu z Markdownu do HTML.
 *
 * Nad běžným Markdownem (GFM, tedy i tabulky) umí tři věci navíc:
 *
 * - Štítek stavu zapsaný `[[kritické]]`. Barvu určuje slovo podle TONES,
 *   neznámé slovo dostane neutrální štítek. Funguje v nadpisu, v tabulce
 *   i v textu, jen ne uvnitř kódu.
 * - Box (třeba položka ceníku) mezi řádky `::: box Popisek` a `:::`.
 *   Popisek je nepovinný, uvnitř je běžný Markdown. Boxy těsně za sebou
 *   se postaví vedle sebe.
 * - Nadpisy druhé úrovně dostanou `id` a tvoří obsah v boční navigaci.
 *
 * Syrové HTML v textu se escapuje. Audit píše jen správce, ale sdílená
 * stránka je veřejná a nemá důvod vykreslit cizí `<script>`.
 */
class AuditMarkdown
{
    /**
     * Slovo ve štítku → tón. Třídy `audit-tag--*` jsou v resources/css/app.css.
     */
    private const TONES = [
        'bad' => ['kritické', 'kritický', 'chybné', 'chybí', 'ne', 'nic', 'nezmíněn', 'prázdná', '503'],
        'warn' => ['vysoké', 'vysoký', 'slabé', 'částečně', 'pozor', 'duplicita', 'duplicitní', 'neověřeno', 'ověřit'],
        'neutral' => ['střední', 'nízké', 'nízký'],
        'good' => ['v pořádku', 'ano', 'dobré', 'zlepšeno', 'aktivní', 'hotovo', '200'],
    ];

    /**
     * @return array{html: string, toc: list<array{id: string, title: string}>}
     */
    public static function render(string $markdown): array
    {
        if (trim($markdown) === '') {
            return ['html' => '', 'toc' => []];
        }

        // Boxy se vytáhnou dřív, než text uvidí převodník, a na jejich místo
        // přijde značka. Vnitřek se převede zvlášť a vrátí se na konci.
        $boxes = [];

        $markdown = preg_replace_callback(
            '/^:::[ \t]*box(?:[ \t]+(.+?))?[ \t]*\R(.*?)\R:::[ \t]*$/msu',
            function (array $m) use (&$boxes): string {
                $boxes[] = ['label' => trim($m[1]), 'body' => $m[2]];

                return "\n\n@@box".(count($boxes) - 1)."@@\n\n";
            },
            str_replace("\r\n", "\n", $markdown),
        );

        $html = self::toHtml($markdown);

        // Značky těsně za sebou tvoří jednu mřížku.
        $html = preg_replace_callback('/(?:<p>@@box\d+@@<\/p>\s*)+/', function (array $m) use ($boxes): string {
            preg_match_all('/@@box(\d+)@@/', $m[0], $ids);

            $inner = collect($ids[1])->map(function (string $id) use ($boxes): string {
                $box = $boxes[(int) $id];
                $label = $box['label'] !== ''
                    ? '<p class="audit-box__label">'.e($box['label']).'</p>'
                    : '';

                return '<div class="audit-box">'.$label.self::toHtml($box['body']).'</div>';
            })->implode('');

            return '<div class="audit-boxes">'.$inner.'</div>'."\n";
        }, $html);

        $toc = [];
        $used = [];

        $html = preg_replace_callback('/<h2>(.*?)<\/h2>/s', function (array $m) use (&$toc, &$used): string {
            $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5));
            $id = Str::slug($title) ?: 'sekce';

            // Dva stejné nadpisy nesmí dostat stejnou kotvu.
            $base = $id;
            for ($i = 2; isset($used[$id]); $i++) {
                $id = $base.'-'.$i;
            }
            $used[$id] = true;

            $toc[] = ['id' => $id, 'title' => $title];

            return '<h2 id="'.$id.'">'.$m[1].'</h2>';
        }, $html);

        return ['html' => $html, 'toc' => $toc];
    }

    /** Markdown → HTML se štítky, obalenými tabulkami a externími odkazy. */
    private static function toHtml(string $markdown): string
    {
        $converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);

        $html = (string) $converter->convert($markdown);

        $html = self::replaceOutsideCode($html, fn (string $chunk): string => preg_replace_callback(
            '/\[\[([^\[\]]{1,40})\]\]/u',
            fn (array $m): string => self::tag($m[1]),
            $chunk,
        ));

        // Široké tabulky se na mobilu posouvají do strany samy, stránka ne.
        $html = str_replace(['<table>', '</table>'], ['<div class="audit-table"><table>', '</table></div>'], $html);

        return preg_replace('/<a href="(https?:\/\/[^"]+)">/', '<a href="$1" target="_blank" rel="noopener">', $html);
    }

    private static function tag(string $word): string
    {
        $word = trim($word);
        $key = mb_strtolower($word);

        $tone = collect(self::TONES)
            ->search(fn (array $words): bool => in_array($key, $words, true)) ?: 'neutral';

        return '<span class="audit-tag audit-tag--'.$tone.'">'.$word.'</span>';
    }

    /**
     * Pustí úpravu jen na části HTML mimo `<code>` a `<pre>`, aby se
     * štítek nerozbalil v ukázce kódu.
     */
    private static function replaceOutsideCode(string $html, callable $callback): string
    {
        $parts = preg_split('/(<pre>.*?<\/pre>|<code>.*?<\/code>)/s', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        return collect($parts)
            ->map(fn (string $part, int $i): string => $i % 2 === 1 ? $part : $callback($part))
            ->implode('');
    }
}
