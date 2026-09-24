<?php

use App\Models\Audit;
use Illuminate\Database\Migrations\Migration;

/**
 * Ceník v auditu Světa Cejlonu jako dva boxy vedle sebe (zápis `::: box`,
 * viz App\Support\AuditMarkdown). Text v database/content/audits/ už nový
 * tvar má, tahle migrace srovná audit, který na webu vznikl dřív.
 *
 * Přepisuje jen nedotčený text. Jakmile ceník někdo upraví v administraci,
 * starý úsek se nenajde a migrace nic neudělá.
 */
return new class extends Migration
{
    private const PUBLIC_TOKEN = 'kb3mcK5MHcNnlO5IcZzBtc4zkjmyfbQq0Tsz05uT';

    public function up(): void
    {
        $this->swap(self::old(), self::new());
    }

    public function down(): void
    {
        $this->swap(self::new(), self::old());
    }

    private function swap(string $from, string $to): void
    {
        $audit = Audit::where('public_token', self::PUBLIC_TOKEN)->first();

        if (! $audit || ! str_contains((string) $audit->body, $from)) {
            return;
        }

        $audit->update(['body' => str_replace($from, $to, $audit->body)]);
    }

    private static function old(): string
    {
        return <<<'MD'
### SEO úklid: 4 900 Kč jednorázově

- Google Search Console, Bing Webmaster Tools, Seznam Webmaster, odeslání sitemap
- 1 786 filtračních stránek a 104 variant pryč z indexu a ze sitemapy
- smazání ukázkového obsahu Upgates (aktuality, rádce, výrobce), noindex pomocných stránek
- `robots.txt` pro AI roboty a dotaz na Upgates kvůli zablokovaným GPTBot a ClaudeBot
- titulky a popisky úvodky, kategorií a obsahových stránek
- šablona: JSON-LD o firmě, oprava hodnocení a recenzí pro hvězdičky ve vyhledávání, Open Graph, alt loga, nadpisy
- stránka Kontakt, opravy víc H1 a překlepů v produktech
- GA4: propojení se Search Console a sledování návštěv z AI asistentů

Hotovo do týdne od dodání přístupu k DNS domény.

### SEO a AI péče: 1 900 Kč měsíčně

Minimálně 3 měsíce.

- **2 odborné články do Rádce** měsíčně (cejlonská skořice vs. kasie, oblasti čaje, louhování…) nebo text kategorie
- **fakta a FAQ u 5 produktů** měsíčně, začíná se nejprodávanějšími
- SEO titulek a popisek pro každý nový produkt
- kontrola indexace a chyb v Search Console
- **krátký měsíční report**: návštěvy z Googlu a Seznamu, dotazy, na které se web zobrazuje, návštěvy z AI a jestli ChatGPT a Perplexity e-shop doporučují

Výsledky SEO se projevují po 2–3 měsících, proto minimální délka 3 měsíce. Pak lze kdykoliv ukončit.

MD;
    }

    private static function new(): string
    {
        return <<<'MD'
::: box Jednorázově
### SEO úklid

**4 900 Kč**

- Google Search Console, Bing Webmaster Tools, Seznam Webmaster, odeslání sitemap
- 1 786 filtračních stránek a 104 variant pryč z indexu a ze sitemapy
- smazání ukázkového obsahu Upgates (aktuality, rádce, výrobce), noindex pomocných stránek
- `robots.txt` pro AI roboty a dotaz na Upgates kvůli zablokovaným GPTBot a ClaudeBot
- titulky a popisky úvodky, kategorií a obsahových stránek
- šablona: JSON-LD o firmě, oprava hodnocení a recenzí pro hvězdičky ve vyhledávání, Open Graph, alt loga, nadpisy
- stránka Kontakt, opravy víc H1 a překlepů v produktech
- GA4: propojení se Search Console a sledování návštěv z AI asistentů

Hotovo do týdne od dodání přístupu k DNS domény.
:::

::: box Měsíčně · minimálně 3 měsíce
### SEO a AI péče

**1 900 Kč** / měsíc

- **2 odborné články do Rádce** měsíčně (cejlonská skořice vs. kasie, oblasti čaje, louhování…) nebo text kategorie
- **fakta a FAQ u 5 produktů** měsíčně, začíná se nejprodávanějšími
- SEO titulek a popisek pro každý nový produkt
- kontrola indexace a chyb v Search Console
- **krátký měsíční report**: návštěvy z Googlu a Seznamu, dotazy, na které se web zobrazuje, návštěvy z AI a jestli ChatGPT a Perplexity e-shop doporučují

Výsledky SEO se projevují po 2–3 měsících, proto minimální délka 3 měsíce. Pak lze kdykoliv ukončit.
:::

MD;
    }
};
