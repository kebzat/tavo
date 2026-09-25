<?php

use App\Models\Audit;
use App\Models\Client;
use Illuminate\Database\Migrations\Migration;

/**
 * Nová kalkulace pro Svět Cejlonu podle Toma (25. 9. 2026): jednorázově jen
 * základní úklid toho nejdůležitějšího za 4 900 Kč, zbytek auditu postupně
 * v rámci měsíční spolupráce za 5 000 Kč, minimálně 3 měsíce. Minimum je
 * výslovné rozhodnutí zakladatele, má přednost před obecným doporučením
 * v docs/BRAND-STRATEGY.md (18.1).
 *
 * Přepíše jen nedotčený ceník. Úpravy z administrace zůstanou.
 */
return new class extends Migration
{
    private const AUDIT_TOKEN = 'kb3mcK5MHcNnlO5IcZzBtc4zkjmyfbQq0Tsz05uT';

    private const CLIENT_SLUG = 'svet-cejlonu';

    private const OLD_OFFER = '::: box Jednorázově
### SEO úklid

**4 900 Kč**

- Google Search Console, Bing Webmaster Tools, Seznam Webmaster, odeslání sitemap
- 1 786 filtračních stránek a 104 variant pryč z indexu a ze sitemapy
- smazání ukázkového obsahu Upgates, noindex pomocných stránek
- `robots.txt` pro AI roboty a dotaz na Upgates kvůli zablokovaným GPTBot a ClaudeBot
- titulky a popisky úvodky, kategorií a obsahových stránek
- šablona: údaje o firmě, oprava recenzí pro hvězdičky ve vyhledávání, náhled při sdílení, nadpisy
- stránka Kontakt, opravy nadpisů a překlepů v produktech
- GA4: propojení se Search Console a měření návštěv z AI asistentů

Hotovo do týdne od dodání přístupu k DNS domény.
:::

::: box Měsíčně
### SEO monitoring

**1 900 Kč** / měsíc

- sledování organického růstu a propadů webu
- doporučení pro úpravy obsahu na webu
- konkrétní checklist pro každý měsíc
- měsíční report návštěvnosti a analytiky webu
- kontrola indexace a chyb v Search Console
- možnost e-mailových konzultací
- WhatsApp skupina pro rychlé diskuze

Výsledky SEO bývají vidět po 2–3 měsících.
:::

> **První 3 měsíce celkem 10 600 Kč** (4 900 Kč úklid + 3 × 1 900 Kč monitoring).

';

    private const NEW_OFFER = '::: box Jednorázově
### Základní úklid

**4 900 Kč**

- Google Search Console, Bing Webmaster Tools a Seznam Webmaster, odeslání sitemap
- 1 786 filtračních stránek a 104 variant pryč z indexu a ze sitemapy
- smazání ukázkového obsahu Upgates, noindex pomocných stránek
- dotaz na Upgates kvůli zablokovaným robotům OpenAI a Anthropic

Hotovo do týdne od dodání přístupu k DNS domény.
:::

::: box Měsíčně · minimálně 3 měsíce
### SEO monitoring

**5 000 Kč** / měsíc

- postupně dotáhneme zbytek auditu: titulky a popisky, údaje o firmě a recenze v šabloně, stránku Kontakt, opravy nadpisů a překlepů
- sledování organického růstu a propadů webu
- doporučení pro úpravy obsahu na webu
- konkrétní checklist pro každý měsíc
- měsíční report návštěvnosti a analytiky webu
- kontrola indexace a chyb v Search Console
- možnost e-mailových konzultací
- WhatsApp skupina pro rychlé diskuze

Výsledky SEO bývají vidět po 2–3 měsících, proto minimálně 3 měsíce.
:::

> **První 3 měsíce celkem 19 900 Kč** (4 900 Kč úklid + 3 × 5 000 Kč). Úkoly z checklistu bereme od nejdůležitějších.

';

    private const OLD_NOTE = 'Nabídka: SEO úklid 4 900 Kč + SEO monitoring 1 900 Kč měsíčně (min. 3 měsíce), bez DPH.';

    private const NEW_NOTE = 'Nabídka: základní úklid 4 900 Kč + 5 000 Kč měsíčně, min. 3 měsíce (monitoring a postupné dotažení auditu).';

    public function up(): void
    {
        $this->swap(self::OLD_OFFER, self::NEW_OFFER, self::OLD_NOTE, self::NEW_NOTE);
    }

    public function down(): void
    {
        $this->swap(self::NEW_OFFER, self::OLD_OFFER, self::NEW_NOTE, self::OLD_NOTE);
    }

    private function swap(string $fromOffer, string $toOffer, string $fromNote, string $toNote): void
    {
        $audit = Audit::where('public_token', self::AUDIT_TOKEN)->first();

        if ($audit && substr_count((string) $audit->body, $fromOffer) === 1) {
            $audit->update(['body' => str_replace($fromOffer, $toOffer, $audit->body)]);
        }

        $client = Client::where('slug', self::CLIENT_SLUG)->first();

        if ($client && str_contains((string) $client->note, $fromNote)) {
            $client->update(['note' => str_replace($fromNote, $toNote, $client->note)]);
        }
    }
};
