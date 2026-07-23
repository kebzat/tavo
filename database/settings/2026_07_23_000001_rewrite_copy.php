<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Přepis textů webu. Původní obsah z designu byl psaný obecně a znělo to jako
 * generovaný text (pomlčky, trojice, „ne X ale Y"). Tahle migrace ho nahrazuje
 * konkrétním textem s lokalitou Hradec Králové a bez vymyšlených čísel.
 *
 * Pravidla pro další úpravy textů: .claude/skills/tavo-copy/SKILL.md
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->set([
            /*
            |------------------------------------------------------------------
            | Web
            |------------------------------------------------------------------
            */
            'site.brand_claim' => 'Weby, e-shopy a reklama z Hradce Králové. Děláme to dva, Pavel a Tom.',
            'site.nav_links' => [
                ['label' => 'Co děláme', 'url' => '/#sluzby'],
                ['label' => 'Proč spolu', 'url' => '/#proc'],
                ['label' => 'Reference', 'url' => '/reference'],
                ['label' => 'Kdo jsme', 'url' => '/#lide'],
            ],
            'site.nav_cta_label' => 'Napsat nám',
            'site.footer_columns' => [
                [
                    'title' => 'Služby',
                    'links' => [
                        ['label' => 'Tvorba webových stránek', 'url' => '/sluzby/tvorba-webovych-stranek'],
                        ['label' => 'Tvorba e-shopu', 'url' => '/sluzby/tvorba-eshopu'],
                        ['label' => 'Reklama a marketing', 'url' => '/sluzby/reklama-a-marketing'],
                        ['label' => 'Správa a rozvoj webu', 'url' => '/sluzby/sprava-a-rozvoj-webu'],
                    ],
                ],
                [
                    'title' => 'TAVO',
                    'links' => [
                        ['label' => 'Reference', 'url' => '/reference'],
                        ['label' => 'Kdo jsme', 'url' => '/#lide'],
                        ['label' => 'Jak pracujeme', 'url' => '/#proces'],
                    ],
                ],
                [
                    'title' => 'Napište si rovnou',
                    'links' => [
                        ['label' => 'Pavel: pavelvcelis.cz', 'url' => 'https://pavelvcelis.cz/'],
                        ['label' => 'Tom: juliatom.cz', 'url' => 'https://juliatom.cz/'],
                    ],
                ],
            ],
            'site.footer_note' => 'Tenhle web jsme si postavili sami. Žádná šablona.',

            /*
            |------------------------------------------------------------------
            | Kontakt
            |------------------------------------------------------------------
            */
            'contact.phone' => '+420 603 732 263',
            'contact.address' => 'Hradec Králové',

            /*
            |------------------------------------------------------------------
            | Homepage
            |------------------------------------------------------------------
            */
            'home.hero_eyebrow' => 'Hradec Králové',
            'home.hero_line_1' => 'Postavíme web',
            // Delší druhý řádek („a pak") nechával na mobilu osamocené slovo.
            'home.hero_line_2' => 'nebo e-shop a',
            'home.hero_line_3' => 'sháníme',
            'home.hero_line_3_accent' => 'zákazníky.',
            'home.hero_perex' => 'Jsme dva. Pavel osm let dělá reklamu a marketing, Tom weby a e-shopy. Domluvíte se přímo s námi, ne s account managerem, který to někam přepošle.',
            'home.hero_cta_primary_label' => 'Chci web nebo e-shop',
            'home.hero_cta_secondary_label' => 'Jak to děláme',

            'home.problem_eyebrow' => 'Kde to nejčastěji drhne',
            'home.problem_title' => "Každý dodavatel splní zadání.\nA web pořád nevydělává.",
            'home.problem_perex' => 'Web dělá jedna firma, reklamu druhá, měření nikdo. Každý se dívá jen na svůj kousek a mezi kliknutím na reklamu a odeslanou objednávkou je pak díra, do které padají peníze.',
            'home.problem_points' => [
                'Pěkný web, na který nikdo nechodí.',
                'Reklama za dvacet tisíc měsíčně vede na stránku, kde se nedá pořádně objednat.',
                'Analytika, do které se od zapnutí nikdo nepodíval.',
            ],

            'home.situations_title' => 'Většina lidí nám píše ve dvou situacích.',
            'home.situations' => [
                [
                    'eyebrow' => 'Začínáme od nuly',
                    'title' => '„Potřebujeme web nebo e-shop."',
                    'text' => 'Nový firemní web, předělávka staré verze, e-shop na Shoptetu nebo WooCommerce, stránka pro jednu kampaň. Postavíme to rovnou tak, aby se na to dala pustit reklama.',
                    'cta_label' => 'Chci nový web →',
                    'cta_url' => '#kontakt',
                    'variant' => 'dark',
                ],
                [
                    'eyebrow' => 'Máme web, ale',
                    'title' => '„Web máme, jen z něj nic nechodí."',
                    'text' => 'Buď na něj nikdo nechodí, nebo chodí a nic neudělá. Projdeme, kde to padá, spravíme web a převezmeme reklamu. Bez toho, abyste všechno zahazovali.',
                    'cta_label' => 'Chci to spravit →',
                    'cta_url' => '#kontakt',
                    'variant' => 'brick',
                ],
            ],

            'home.services_title' => 'Co děláme',
            'home.services_perex' => 'Čtyři věci, které spolu souvisí víc, než se zvenku zdá.',

            'home.cases_title' => 'Na čem jsme dělali',
            'home.cases_link_label' => 'Všechny projekty →',

            'home.loop_title' => 'Proč to dohromady vyjde líp',
            'home.loop_perex' => 'Reklama přivede lidi, web je přesvědčí a z měření víme, co příště udělat jinak. Každé další kolo je díky tomu levnější než to předchozí.',
            'home.loop_items' => [
                ['label' => 'Přivede', 'title' => 'Marketing', 'text' => 'Kampaně na Facebooku, Instagramu a Googlu. Cílíme na lidi, kteří u vás opravdu můžou nakoupit.'],
                ['label' => 'Přesvědčí', 'title' => 'Web', 'text' => 'Vysvětlí, proč vy a ne někdo o dvě ulice dál. Zbytek dotáhne formulář nebo košík.'],
                ['label' => 'Ukáže', 'title' => 'Data', 'text' => 'Měření řekne, která kampaň se vyplatila a na které stránce lidi končí.'],
                ['label' => 'Zlepší', 'title' => 'Rozvoj', 'text' => 'Podle toho měníme texty, stránky i rozpočty. A jede se další kolo.'],
            ],

            'home.founders_title' => 'Pavel a Tom. Nikdo další.',
            'home.founders_perex' => 'Mluvíte přímo s lidmi, kteří tu práci dělají. Odpověď na dotaz tak netrvá tři dny a nejde přes tři e-maily.',
            'home.founders_intro' => 'Pavel osm let řeší reklamu a sociální sítě pro e-shopy i firmy ve službách, Tom staví weby a e-shopy pro menší firmy z Hradce a okolí. Dlouho jsme si posílali práci navzájem, tak jsme z toho udělali TAVO.',

            'home.process_title' => 'Jak to probíhá',

            'home.cta_eyebrow' => 'Ozvěte se',
            'home.cta_title' => 'Napište nám, co potřebujete.',
            'home.cta_perex' => 'Stačí pár vět o tom, co děláte a co od webu čekáte. Ozveme se do dvou pracovních dnů a rovnou napíšeme, jestli vám umíme pomoct. Když ne, doporučíme někoho, kdo ano.',

            /*
            |------------------------------------------------------------------
            | SEO
            |------------------------------------------------------------------
            */
            'seo.default_title' => 'Tvorba webů, e-shopů a reklama',
            'seo.default_description' => 'Postavíme vám web nebo e-shop a rozjedeme reklamu, která na něj přivede zákazníky. Dva lidé z Hradce Králové, žádná agentura mezi tím.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function set(array $values): void
    {
        foreach ($values as $property => $value) {
            $this->migrator->update($property, fn () => $value);
        }
    }
};
