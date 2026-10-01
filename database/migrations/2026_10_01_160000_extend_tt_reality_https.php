<?php

use App\Support\ProposalAdditions;
use Illuminate\Database\Migrations\Migration;

/**
 * TTreality: web se dá otevřít bez zabezpečení a stránka s kontakty je
 * zakázaná pro vyhledávače. Ověřeno 1. 10. 2026: http://ttreality.cz
 * přesměruje 301 na http://www.ttreality.cz a ta vrátí 200 bez šifrování
 * (žádné přesměrování na https, žádné HSTS). Certifikát Let's Encrypt
 * platí pro ttreality.cz i www do 10. 12. 2026, https verze běží bez
 * nezabezpečených zdrojů. Odpovídají všechny čtyři varianty adresy,
 * canonical ukazuje na https://ttreality.cz/. V robots.txt je
 * „Disallow: /kontakty.html“. Tom na to narazil na mobilu v anonymním okně.
 *
 * Jen přidává, úpravy z nástrojů zůstanou (viz ProposalAdditions).
 *
 * Pravidla pro psaní textů: .claude/skills/tavo-copy/SKILL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        ProposalAdditions::apply('tt-reality', [
            'findings' => [
                [
                    'priority' => 'urgent',
                    'tone' => 'problem',
                    'title' => 'Web se dá otevřít bez zabezpečení',
                    'body' => 'Když někdo napíše do prohlížeče jen ttreality.cz, web ho pošle na http://www.ttreality.cz a stránku načte '
                        .'bez šifrování. Prohlížeč pak u adresy ukáže Nezabezpečeno, na mobilu v anonymním okně jsme to viděli sami. '
                        .'Certifikát přitom máte platný a zabezpečená verze funguje, jen na ni web návštěvníky nepřesměruje. '
                        .'U realitky, kde lidé nechávají kontakt kvůli prodeji domu, takové varování ubírá důvěru. '
                        .'K tomu web odpovídá na čtyřech adresách (s www i bez, zabezpečeně i bez) a Googlu hlásí jako hlavní jinou, '
                        .'než na kterou přesměrovává (ověřeno 1. 10. 2026).',
                ],
                [
                    '_after' => 'Vyhledávače mají k webu zbytečně ztížený přístup',
                    'priority' => 'important',
                    'tone' => 'problem',
                    'title' => 'Stránku s kontakty mají vyhledávače zakázanou',
                    'body' => 'V souboru robots.txt, kterým web vyhledávačům říká, kam smí, je zakázaná stránka kontakty.html. '
                        .'Kdo hledá kontakt na vaši kancelář, ji tak ve výsledcích nemusí najít. Pokud to není záměr, jde o jeden řádek.',
                ],
            ],
            'recommendations' => [
                [
                    '_after' => 'Opravit to hlavní do 14 dní',
                    'who' => 'Tom',
                    'title' => 'Jedna zabezpečená adresa',
                    'body' => 'Všechny varianty adresy přesměrujeme na jednu zabezpečenou, nastavíme, aby si ji prohlížeče pamatovaly, '
                        .'a Googlu budeme hlásit tutéž. K tomu povolíme vyhledávačům stránku s kontakty. '
                        .'Je to drobná úprava na serveru, vzhled webu se nemění.',
                ],
            ],
            'steps' => [
                [
                    '_after' => 'Rychlé opravy',
                    'when' => '1. týden',
                    'title' => 'Zabezpečená adresa a kontakty pro Google',
                    'body' => 'Přesměrování na https, jedna hlavní adresa, povolená stránka s kontakty.',
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
