<?php

use App\Models\Audit;
use App\Models\Client;
use Illuminate\Database\Migrations\Migration;

/**
 * Měsíční balíček v nabídce pro Svět Cejlonu je místo „SEO a AI péče“
 * „SEO monitoring“ podle Pavla: sledování, doporučení, report a konzultace.
 * Psaní článků v něm není, objednává se zvlášť.
 *
 * Podle docs/BRAND-STRATEGY.md (6.1 a 18.1) zároveň mizí „bez DPH“, dokud
 * není jasné, kdo fakturuje, a povinné minimum tří měsíců.
 *
 * Každá náhrada proběhne, jen když je původní text beze změny.
 */
return new class extends Migration
{
    private const AUDIT_TOKEN = 'kb3mcK5MHcNnlO5IcZzBtc4zkjmyfbQq0Tsz05uT';

    private const CLIENT_SLUG = 'svet-cejlonu';

    /** @var list<array{0: string, 1: string}> */
    private const AUDIT = [
        [
            '### SEO a AI péče

**1 900 Kč** / měsíc

- **2 odborné články do Rádce** měsíčně nebo text kategorie
- **fakta a otázky u 5 produktů** měsíčně, od nejprodávanějších
- SEO titulek a popisek pro každý nový produkt
- kontrola indexace a chyb v Search Console
- **krátký měsíční report**: návštěvy z Googlu a Seznamu, dotazy, na které se web zobrazuje, návštěvy z AI a jestli e-shop doporučuje ChatGPT a Perplexity
',
            '### SEO monitoring

**1 900 Kč** / měsíc

- sledování organického růstu a propadů webu
- doporučení pro úpravy obsahu na webu
- konkrétní checklist pro každý měsíc
- měsíční report návštěvnosti a analytiky webu
- kontrola indexace a chyb v Search Console
- možnost e-mailových konzultací
- WhatsApp skupina pro rychlé diskuze
',
        ],
        [
            '> **První 3 měsíce celkem 10 600 Kč** (4 900 Kč úklid + 3 × 1 900 Kč péče). Za tu dobu přibude 6 článků nebo textů kategorií a fakta s otázkami u 15 produktů.',
            '> **První 3 měsíce celkem 10 600 Kč** (4 900 Kč úklid + 3 × 1 900 Kč monitoring).',
        ],
        [
            '| Článek do Rádce navíc | 600 Kč / ks |',
            '| Článek do Rádce | 600 Kč / ks |',
        ],
        [
            '## Cenová nabídka

Ceny jsou bez DPH.

::: box Jednorázově',
            '## Cenová nabídka

::: box Jednorázově',
        ],
        [
            '::: box Měsíčně · minimálně 3 měsíce
',
            '::: box Měsíčně
',
        ],
        [
            'Výsledky SEO jsou vidět po 2–3 měsících, proto minimálně 3 měsíce. Pak se dá kdykoliv skončit.',
            'Výsledky SEO bývají vidět po 2–3 měsících.',
        ],
    ];

    private const NOTE = ['péče 1 900 Kč měsíčně', 'SEO monitoring 1 900 Kč měsíčně'];

    public function up(): void
    {
        $this->apply(fn (array $pair): array => $pair);
    }

    public function down(): void
    {
        $this->apply(fn (array $pair): array => [$pair[1], $pair[0]]);
    }

    private function apply(callable $direction): void
    {
        $audit = Audit::where('public_token', self::AUDIT_TOKEN)->first();

        if ($audit) {
            $body = (string) $audit->body;

            foreach (self::AUDIT as $pair) {
                [$from, $to] = $direction($pair);

                if (substr_count($body, $from) === 1) {
                    $body = str_replace($from, $to, $body);
                }
            }

            $audit->update(['body' => $body]);
        }

        $client = Client::where('slug', self::CLIENT_SLUG)->first();
        [$from, $to] = $direction(self::NOTE);

        if ($client && str_contains((string) $client->note, $from)) {
            $client->update(['note' => str_replace($from, $to, $client->note)]);
        }
    }
};
