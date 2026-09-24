<?php

namespace App\Support;

use Illuminate\Support\Str;
use League\CommonMark\GithubFlavoredMarkdownConverter;

/**
 * Převod textu auditu z Markdownu do HTML.
 *
 * Nad běžným Markdownem (GFM, tedy i tabulky) umí dvě věci navíc:
 *
 * - Štítek stavu zapsaný `[[kritické]]`. Barvu určuje slovo podle TONES,
 *   neznámé slovo dostane neutrální štítek. Funguje v nadpisu, v tabulce
 *   i v textu, jen ne uvnitř kódu.
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

        $html = preg_replace('/<a href="(https?:\/\/[^"]+)">/', '<a href="$1" target="_blank" rel="noopener">', $html);

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
