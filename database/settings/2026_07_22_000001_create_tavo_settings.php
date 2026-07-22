<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Výchozí obsah webu — přepis 1:1 z původního designu (design-source/*.dc.html).
 * Po nasazení si obsah přebírá klient ve Filamentu; tato migrace je jen startovní stav.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        /*
        |----------------------------------------------------------------------
        | Web
        |----------------------------------------------------------------------
        */
        $this->migrator->add('site.brand_name', 'TAVO');
        $this->migrator->add('site.brand_claim', 'Weby, e-shopy a marketing, které spolu táhnou. Malý seniorní tým — Pavel & Tom.');
        $this->migrator->add('site.nav_links', [
            ['label' => 'Co umíme', 'url' => '/#sluzby'],
            ['label' => 'Proč my', 'url' => '/#proc'],
            ['label' => 'Reference', 'url' => '/reference'],
            ['label' => 'Lidé', 'url' => '/#lide'],
        ]);
        $this->migrator->add('site.nav_cta_label', 'Poptat projekt');
        $this->migrator->add('site.nav_cta_url', '/#kontakt');
        $this->migrator->add('site.footer_columns', [
            [
                'title' => 'Web',
                'links' => [
                    ['label' => 'Co umíme', 'url' => '/#sluzby'],
                    ['label' => 'Reference', 'url' => '/reference'],
                    ['label' => 'Lidé', 'url' => '/#lide'],
                ],
            ],
            [
                'title' => 'Kontakt',
                'links' => [
                    ['label' => 'ahoj@tavo.cz', 'url' => 'mailto:ahoj@tavo.cz'],
                    ['label' => 'Poptat projekt', 'url' => '/#kontakt'],
                ],
            ],
            [
                'title' => 'Zakladatelé samostatně',
                'links' => [
                    ['label' => 'pavelvcelis.cz →', 'url' => 'https://pavelvcelis.cz/'],
                    ['label' => 'juliatom.cz →', 'url' => 'https://juliatom.cz/'],
                ],
            ],
        ]);
        $this->migrator->add('site.footer_note', 'Vytvořeno se strategií i kódem.');
        $this->migrator->add('site.copyright', '© '.date('Y').' TAVO');

        /*
        |----------------------------------------------------------------------
        | Kontakt
        |----------------------------------------------------------------------
        */
        $this->migrator->add('contact.email', 'ahoj@tavo.cz');
        $this->migrator->add('contact.phone', '+420 000 000 000');
        $this->migrator->add('contact.company_name', 'TAVO');
        $this->migrator->add('contact.ico', '');
        $this->migrator->add('contact.dic', '');
        $this->migrator->add('contact.address', '');
        $this->migrator->add('contact.socials', []);
        $this->migrator->add('contact.lead_recipients', [
            'ahoj@tavo.cz',
        ]);

        /*
        |----------------------------------------------------------------------
        | Homepage
        |----------------------------------------------------------------------
        */
        $this->migrator->add('home.hero_eyebrow', 'Dva senioři · Jeden funkční celek');
        $this->migrator->add('home.hero_line_1', 'Weby, e-shopy');
        $this->migrator->add('home.hero_line_2', 'a marketing, které');
        $this->migrator->add('home.hero_line_3', 'spolu');
        $this->migrator->add('home.hero_line_3_accent', 'táhnou.');
        $this->migrator->add('home.hero_perex', 'Nemusíte řídit agenturu, kodéra a analytika zvlášť. Marketing přivede správné lidi, web je přesvědčí — a my na obojím pracujeme jako jeden tým, se kterým mluvíte přímo.');
        $this->migrator->add('home.hero_cta_primary_label', 'Chci nový web nebo e-shop');
        $this->migrator->add('home.hero_cta_primary_url', '#kontakt');
        $this->migrator->add('home.hero_cta_secondary_label', 'Rozjet marketing');
        $this->migrator->add('home.hero_cta_secondary_url', '#proc');

        $this->migrator->add('home.problem_eyebrow', 'Kde to nejčastěji drhne');
        $this->migrator->add('home.problem_title', "Každý si odvede svůj kus.\nZa výsledek neručí nikdo.");
        $this->migrator->add('home.problem_perex', 'Web dělá jedna firma, reklamu druhá, analytiku třetí. Každý dodavatel splní své zadání — ale nikdo se nedívá na celou cestu od prvního kliknutí po objednávku. A tam se ztrácí peníze.');
        $this->migrator->add('home.problem_points', [
            'Hezký web, na který ale nechodí správní lidé.',
            'Zaplacené reklamy, které vedou na web, co neumí přesvědčit.',
            'Data, kterým nikdo nerozumí a podle kterých se nic nemění.',
        ]);

        $this->migrator->add('home.situations_title', 'Ať jste kdekoliv, začneme tam, kde jste teď.');
        $this->migrator->add('home.situations', [
            [
                'eyebrow' => 'Nový projekt',
                'title' => '„Potřebujeme nový web nebo e-shop."',
                'text' => 'Nový firemní web, redesign zastaralého, e-shop nebo kampaňová landing page. Postavíme to od základu — s marketingem v hlavě od první čáry.',
                'cta_label' => 'Postavit něco nového →',
                'cta_url' => '#kontakt',
                'variant' => 'dark',
            ],
            [
                'eyebrow' => 'Rozvoj & růst',
                'title' => '„Web máme, ale nefunguje podle představ."',
                'text' => 'Chybí návštěvnost, konverze nebo měření. Převezmeme marketing, doladíme web, nastavíme data a dlouhodobě to posouváme dál.',
                'cta_label' => 'Zlepšit, co máme →',
                'cta_url' => '#kontakt',
                'variant' => 'brick',
            ],
        ]);

        $this->migrator->add('home.services_title', 'Co umíme');
        $this->migrator->add('home.services_perex', 'Tři oblasti, jeden tým. Vždy propojené — ne tři oddělení, která si posílají tikety.');

        $this->migrator->add('home.cases_title', 'Vybrané projekty');
        $this->migrator->add('home.cases_link_label', 'Všechny reference →');

        $this->migrator->add('home.loop_title', 'Proč to dává smysl mít pod jednou střechou');
        $this->migrator->add('home.loop_perex', 'Marketing, web, data a rozvoj nejsou čtyři služby. Je to jeden kruh, který se pořád dokola zlepšuje.');
        $this->migrator->add('home.loop_items', [
            ['label' => 'Přivede', 'title' => 'Marketing', 'text' => 'Přivede relevantní návštěvníky — ne jen kliky, ale lidi, u kterých dává obchod smysl.'],
            ['label' => 'Přesvědčí', 'title' => 'Web', 'text' => 'Buduje důvěru a mění zájem v poptávku, rezervaci nebo objednávku.'],
            ['label' => 'Ukáže', 'title' => 'Data', 'text' => 'Měření ukáže, co skutečně funguje a kde se peníze ztrácí.'],
            ['label' => 'Zlepší', 'title' => 'Rozvoj', 'text' => 'Průběžně upravujeme web i kampaně — a celý kruh se roztáčí znovu, líp.'],
        ]);

        $this->migrator->add('home.founders_title', 'Dva lidé. Žádná anonymní agentura.');
        $this->migrator->add('home.founders_perex', 'Na projektu pracují přímo ti, se kterými mluvíte. Rozhodnutí padají rychle a bez tiché pošty přes account managera.');
        $this->migrator->add('home.founders_intro', 'Nejsme velká agentura. Na projektu pracují přímo ti dva, se kterými mluvíte — a táhnou ho celý, od strategie a reklamy až po hotový web a jeho měření.');

        $this->migrator->add('home.process_title', 'Jak spolu pracujeme');

        $this->migrator->add('home.cta_eyebrow', 'Pojďme si promluvit');
        $this->migrator->add('home.cta_title', 'Řekněte nám, kam se chcete dostat.');
        $this->migrator->add('home.cta_perex', 'Nový web, e-shop, marketing nebo zlepšení toho, co už máte. Napište pár vět o vašem podnikání — ozveme se do dvou pracovních dnů.');

        /*
        |----------------------------------------------------------------------
        | SEO
        |----------------------------------------------------------------------
        */
        $this->migrator->add('seo.default_title', 'Weby, e-shopy a marketing, které spolu táhnou');
        $this->migrator->add('seo.title_suffix', ' | TAVO');
        $this->migrator->add('seo.default_description', 'Dva senioři, jeden funkční celek. Stavíme weby a e-shopy a zároveň vedeme marketing, který na ně přivádí správné lidi.');
        $this->migrator->add('seo.og_image', null);
        $this->migrator->add('seo.gtm_id', null);
        $this->migrator->add('seo.indexable', true);
    }
};
