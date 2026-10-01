<?php

use App\Support\ProposalAdditions;
use Illuminate\Database\Migrations\Migration;

/**
 * DOKiDO: SEO a cookie lišta. Ověřeno 1. 10. 2026 na dokido.cz v čistém
 * prohlížeči bez kliknutí do lišty: úvodní stránka bez H1, kategorie
 * Svátky a Vánoce bez popisu a textu, zhruba polovina obrázků bez alt,
 * pořád se načítá Universal Analytics. Před souhlasem se spustí Smartlook,
 * retargeting Seznamu a skripty Facebooku, Google čeká (Consent Mode).
 *
 * Jen přidává, úpravy z nástrojů zůstanou (viz ProposalAdditions).
 *
 * Pravidla pro psaní textů: .claude/skills/tavo-copy/SKILL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        ProposalAdditions::apply('dokido', [
            'findings' => [
                [
                    'priority' => 'urgent',
                    'tone' => 'problem',
                    'title' => 'Nahrávání návštěv a retargeting běží ještě před souhlasem',
                    'body' => 'Hned při otevření webu, bez jakéhokoli kliknutí v cookie liště, se spustí Smartlook, který nahrává, '
                        .'co návštěvník na webu dělá, dál retargeting Seznamu a skripty Facebooku. Nástroj na vyskakovací okna si uloží '
                        .'cookies včetně IP adresy návštěvníka. Podle českého zákona o elektronických komunikacích a pravidel EU smí tohle '
                        .'všechno začít až po souhlasu. Lišta přitom nabízí jen Souhlasím a malé Nastavení a píše o pohodlném '
                        .'prohlížení a analýze, o reklamě ani slovo. Google Analytics a Google Ads máte nastavené správně, '
                        .'na souhlas čekají (ověřeno 1. 10. 2026).',
                ],
                [
                    'priority' => 'important',
                    'tone' => 'opportunity',
                    'title' => 'Vánoce a kategorie podle počtu dětí jsou pro Google skoro prázdné',
                    'body' => 'Kategorie Svátky a Vánoce nemá popis pro vyhledávače ani úvodní text a v titulku je jen „Magnetky | Svátky“. '
                        .'Kategorie Pro 1 dítě, podle které rodiče vybírají nejčastěji, má jedinou větu „Zde najdete všechny naše kalendáře“. '
                        .'Úvodní stránka nemá hlavní nadpis a zhruba polovina obrázků nemá popisek. Přitom to umíte: '
                        .'Kalendáře a plánovače i Magnetky mají dlouhé texty, které radí, a adventní kalendář má dobrý titulek '
                        .'i hodnocení, které Google umí ukázat ve výsledcích.',
                ],
                [
                    'priority' => 'later',
                    'tone' => 'problem',
                    'title' => 'Web pořád načítá vypnutý Universal Analytics',
                    'body' => 'Vedle nového Google Analytics se na každé stránce načítá i starý Universal Analytics, který Google v roce 2024 '
                        .'vypnul. Nic už neměří, jen zdržuje načítání. Stačí ho z webu odebrat.',
                ],
            ],
            'recommendations' => [
                [
                    '_after' => 'Opravit problémová místa na webu do 14 dní',
                    'who' => 'Tom',
                    'title' => 'Cookie lišta a měření podle pravidel',
                    'body' => 'Lišta s tlačítkem pro odmítnutí vedle souhlasu a s pravdivým textem. Smartlook, Seznam, Facebook a vyskakovací '
                        .'okna spuštěné až po souhlasu, stejně jako už to funguje u Googlu. Nejsme právníci, technickou stránku ale umíme '
                        .'nastavit tak, aby odpovídala pravidlům a data z reklam byla čistá.',
                ],
                [
                    '_after' => 'Advent na úvodní stránku hned teď',
                    'who' => 'Tom',
                    'title' => 'SEO hlavních kategorií před Vánoci',
                    'body' => 'Texty a popisy pro Google u Svátků a Vánoc a u kategorií podle počtu dětí, ve stejném duchu, v jakém už máte '
                        .'Kalendáře a plánovače. K tomu hlavní nadpis na úvodní stránku, popisky obrázků a pryč se starým Universal Analytics.',
                ],
            ],
            'steps' => [
                [
                    '_after' => 'Rychlé opravy a advent',
                    'when' => '1. týden',
                    'title' => 'Cookie lišta a měření',
                    'body' => 'Tlačítko pro odmítnutí, nástroje třetích stran až po souhlasu, pryč se starým Universal Analytics.',
                    'later' => false,
                ],
                [
                    '_after' => 'Detail kalendáře',
                    'when' => '2.–3. týden',
                    'title' => 'SEO kategorií',
                    'body' => 'Svátky a Vánoce, kategorie podle počtu dětí, hlavní nadpis a popisky obrázků.',
                    'later' => false,
                ],
            ],
        ]);
    }

    public function down(): void
    {
        // Body mohli mezitím upravit v nástrojích, mažou se tam.
    }
};
