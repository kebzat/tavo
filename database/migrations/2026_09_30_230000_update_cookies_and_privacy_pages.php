<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Stránky Cookies a Ochrana osobních údajů po přidání Meta Pixelu,
 * Microsoft Clarity a GA4. Původní text sliboval jen anonymní měření
 * a odvolání souhlasu smazáním úložiště prohlížeče.
 *
 * Nový text je v database/content/, odkud ho bere i ContentSeeder.
 * Chová se jako `replaceIfUntouched()` u settings migrací: přepíše jen
 * stránku, na které pořád stojí původní text ze seederu. Když ji správce
 * mezitím upravil, zůstane jeho verze a text je potřeba doplnit ručně
 * v administraci.
 */
return new class extends Migration
{
    private const ORIGINAL = [
        'cookies' => <<<'HTML'
<h2>Nezbytné cookies</h2>
<p>Web používá technické cookies nutné pro jeho běh, tedy udržení relace a ochranu formuláře před zneužitím. Tyto cookies nelze vypnout a nesbírají údaje pro marketing.</p>
<h2>Analytické cookies</h2>
<p>Pokud udělíte souhlas, načteme měřicí kód, který nám anonymně ukazuje, jak se web používá. Bez souhlasu se nenačte vůbec.</p>
<h2>Změna souhlasu</h2>
<p>Souhlas můžete kdykoliv odvolat vymazáním úložiště webu ve svém prohlížeči. Banner se pak zobrazí znovu.</p>
HTML,
        'ochrana-osobnich-udaju' => <<<'HTML'
<h2>Kdo je správcem údajů</h2>
<p>Správcem osobních údajů je Taveo. Kontaktovat nás můžete na e-mailu uvedeném v patičce webu.</p>
<h2>Jaké údaje zpracováváme</h2>
<p>Zpracováváme údaje, které nám sami vyplníte v poptávkovém formuláři: jméno, firmu, e-mail, telefon a text zprávy. Dále technické údaje nutné k ochraně před spamem (IP adresa, prohlížeč).</p>
<h2>Proč je zpracováváme</h2>
<p>Výhradně proto, abychom mohli odpovědět na vaši poptávku a případně s vámi uzavřít smlouvu. Údaje nepředáváme třetím stranám ani je nepoužíváme k reklamnímu oslovování.</p>
<h2>Jak dlouho je uchováváme</h2>
<p>Poptávky uchováváme po dobu tří let od posledního kontaktu, pak je mažeme.</p>
<h2>Vaše práva</h2>
<p>Máte právo na přístup ke svým údajům, jejich opravu, výmaz i vznesení námitky proti zpracování. Stačí nám napsat.</p>
HTML,
    ];

    public function up(): void
    {
        foreach (self::ORIGINAL as $slug => $original) {
            $page = DB::table('pages')->where('slug', $slug)->first();
            $blocks = $page ? json_decode($page->blocks ?? '[]', true) : null;

            $untouched = is_array($blocks)
                && count($blocks) === 1
                && ($blocks[0]['type'] ?? null) === 'text'
                && $this->normalize($blocks[0]['data']['body'] ?? '') === $this->normalize($original);

            if (! $untouched) {
                continue;
            }

            $blocks[0]['data']['body'] = file_get_contents(database_path("content/{$slug}.html"));

            DB::table('pages')->where('slug', $slug)->update([
                'blocks' => json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
        }
    }

    /** Editor v administraci může přeskládat mezery a zalomení, obsah tím nemění. */
    private function normalize(string $html): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $html));
    }
};
