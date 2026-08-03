<?php

use App\Support\ContentSettingsMigration;

/**
 * Odstranění trojí repetice na homepage.
 *
 * Sekce „Kde to nejčastěji drhne", perex u služeb i sekce „Proč to dohromady
 * vyjde líp" říkaly to samé: když web dělá jeden dodavatel a reklamu druhý,
 * vzniká mezi tím díra. Vysvětlování zůstává jen v sekci s kruhem, první černá
 * sekce se překlápí na zkušenost klienta a perex u služeb na prostou informaci.
 *
 * Pravidla pro psaní textů: .claude/skills/tavo-copy/SKILL.md
 */
return new class extends ContentSettingsMigration
{
    public function up(): void
    {
        // Vědomý přepis vlastních textů. Web zatím nikdo needituje v administraci,
        // po spuštění by tohle patřilo do `replaceIfUntouched()`.
        $this->replace([
            // Hero: „account manager" je agenturní žargon, který cílový čtenář nepoužívá.
            'home.hero_perex' => 'Pavel osm let dělá reklamu a marketing, Tom weby a e-shopy. Když napíšete, odpovídá jeden z nás dvou.',

            // Černá sekce nad dvěma situacemi: nově o tom, co zažil klient,
            // ne o tom, jak si dodavatelé předávají práci.
            'home.problem_title' => "Web je hotový\na tím to skončí.",
            'home.problem_perex' => 'Tohle slyšíme pořád dokola. Zaplatíte za web, dostanete odkaz a od té chvíle se o něj nikdo nestará. Odkud na něj mají lidi chodit, se neřešilo. Jestli z něj chodí poptávky, taky ne. Za rok zjistíte, že nejčastější návštěvník jste vy sami, když kontrolujete, že to ještě běží.',

            'home.services_perex' => 'Čtyři věci. Někdo od nás chce jenom jednu, u někoho se to nabalí postupně.',
        ]);
    }
};
