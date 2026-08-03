<?php

use App\Support\ContentSettingsMigration;

/**
 * Sekce „Kdo jsme" doplněná o lidi kolem Pavla a Toma.
 *
 * Jádro zůstává dvoučlenné (Pavel marketing, Tom vývoj), přibývá blok
 * o specialistech na volné noze, které umíme doporučit. Původní nadpis
 * „Pavel a Tom. Nikdo další." si s tím protiřečil, proto se mění.
 *
 * Pravidla pro psaní textů: .claude/skills/tavo-copy/SKILL.md
 */
return new class extends ContentSettingsMigration
{
    public function up(): void
    {
        // Vědomý přepis textů, které jsme sami nasadili.
        $this->replace([
            'home.founders_title' => 'Pavel a Tom. A pár lidí, kterým věříme.',
            'home.founders_perex' => 'Marketing vede Pavel, weby a e-shopy Tom. Bavíte se rovnou s tím, kdo tu práci dělá.',
        ]);

        // Nová pole: hodnoty jsou jen výchozí, editaci v administraci nepřebijou.
        $this->add([
            'home.founders_network_title' => 'Když je potřeba někdo další',
            'home.founders_network_text' => 'Jednomu klientovi jsme sháněli člověka na reels a zaškolení na obsah. Nedělali jsme, že to umíme sami. Napsali jsme kamarádce, která tohle dělá roky, a vzala to. Takhle to chodí i u fotek, grafiky nebo když má někdo srovnat značku. Jsou to lidi na volné noze, se kterými jsme na něčem dělali a víme, jak odvádějí práci. Domluvu i fakturu řešíte přímo s nimi, nikdo nesedí mezi vámi. Výjimka jsou kreativy a videa, která jdou rovnou do kampaní nebo na e-shop od nás. Za ty ručíme, tak si je bereme pod sebe.',
            'home.founders_network_items' => [
                'Foto',
                'Video a reels',
                'UGC',
                'Grafika',
                'Značka a strategie',
                'Texty',
            ],
        ]);
    }
};
