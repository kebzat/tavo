<?php

use App\Support\ProposalAdditions;
use Illuminate\Database\Migrations\Migration;

/**
 * Šijeme dětem: cookies, SEO a nový vzhled jako součást přechodu na nový
 * e-shop. Ověřeno 1. 10. 2026 na sijemdetem.cz v čistém prohlížeči: web
 * nemá cookie lištu vůbec, Google Analytics 4 (G-K2SHE79K9B) i starý
 * Universal Analytics (UA-47391417-1) měří hned při otevření bez Consent
 * Mode a uloží _ga, _gid. Jiné reklamní nástroje (Facebook, Seznam) jsme
 * nenašli. SEO: úvodní stránka má titulek i H1 „Nabídka“, úvodní stránka
 * a kategorie (Metráž, BIO Bavlna, Látkové plenky) bez meta description,
 * žádná strukturovaná data, bez alt 10 z 15 obrázků na úvodní stránce,
 * 15 z 21 v Metráži, 13 z 18 na detailu látky, sitemap.xml vrací 404.
 * Váha je v pořádku (0,2 MB na mobilu při otevření, 0,5 MB celkem,
 * 55 požadavků), proto ji nezmiňujeme. Redesign není volitelný doplněk,
 * vzhled vzniká s novým e-shopem, který už koncept doporučuje.
 *
 * Jen přidává, úpravy z nástrojů zůstanou (viz ProposalAdditions).
 *
 * Pravidla pro psaní textů: .claude/skills/tavo-copy/SKILL.md
 */
return new class extends Migration
{
    public function up(): void
    {
        ProposalAdditions::apply('sijeme-detem', [
            'findings' => [
                [
                    'priority' => 'urgent',
                    'tone' => 'problem',
                    'title' => 'Web nemá cookie lištu a Google Analytics měří bez souhlasu',
                    'body' => 'Cookie lištu jsme na webu nenašli. Hned při otevření si Google Analytics uloží své cookies a začne měřit '
                        .'každého návštěvníka, a to dvakrát: novou verzí a vedle ní i starým Universal Analytics, který Google v roce 2024 '
                        .'vypnul a data z něj už nezpracovává. Podle českého zákona o elektronických komunikacích a pravidel EU smí měření '
                        .'s cookies začít až po souhlasu. Pixel Facebooku ani měření Seznamu na webu neběží (ověřeno 1. 10. 2026).',
                ],
                [
                    'priority' => 'important',
                    'tone' => 'opportunity',
                    'title' => 'Google o e-shopu ví jen „Nabídka“',
                    'body' => 'Úvodní stránka má v titulku i v hlavním nadpisu jediné slovo „Nabídka“. Kategorie se v Googlu ukážou jako '
                        .'„METRÁŽ | Sijemdetem.cz“ a ani ony, ani úvodní stránka nemají popis pro vyhledávače, takže si ho Google poskládá sám. '
                        .'Strukturovaná data chybí, Google tak nemá z čeho ukázat u látek cenu a dostupnost přímo ve výsledcích.Většina obrázků nemá popisek '
                        .'(na úvodní stránce 10 z 15) a web nemá mapu stránek pro vyhledávače. Obsah přitom máte silný: detail látky '
                        .'má popis se složením a pod ním stovky slov z poradny. To je pro Google cenné a v novém e-shopu to využijeme.',
                ],
            ],
            'recommendations' => [
                [
                    '_after' => 'Uklidit a zazálohovat web do 14 dní',
                    'who' => 'Tom',
                    'title' => 'Měření jen se souhlasem už na starém webu',
                    'body' => 'Při úklidu odpojíme starý Universal Analytics a přidáme jednoduchou lištu s tlačítky Souhlasím a Odmítnout. '
                        .'Google Analytics se spustí až po souhlasu. Nový e-shop bude mít lištu nastavenou od prvního dne. Nejsme právníci, '
                        .'technickou stránku ale umíme nastavit tak, aby odpovídala pravidlům a čísla z měření byla čistá.',
                ],
                [
                    '_after' => 'Nákup látek bez návodu',
                    'who' => 'Tom',
                    'title' => 'Nový e-shop připravený pro Google',
                    'body' => 'Titulky a popisy pro vyhledávače u úvodní stránky, kategorií látek a hotových výrobků, strukturovaná data '
                        .'s cenou za metr, popisky obrázků a mapa stránek. Komentáře z poradny převedeme jako text u látek, '
                        .'staré adresy přesměrujeme. Do SEO starého Drupalu už neinvestujeme.',
                ],
                [
                    '_after' => 'Fotky a texty pro nový web',
                    'who' => 'Tom',
                    'title' => 'Nový vzhled vznikne s novým e-shopem',
                    'body' => 'Návrh výš ukazuje, jak by mohl nový e-shop vypadat. Vzhled navrhujeme rovnou při přechodu na nový systém, '
                        .'takže se nic nedělá dvakrát. Starý Drupal do té doby jen uklidíme, aby v klidu prodával do Vánoc, a do jeho '
                        .'vzhledu už nic nedáváme.',
                ],
            ],
            'steps' => [
                [
                    '_after' => 'Úklid a záloha',
                    'when' => '1.–2. týden',
                    'title' => 'Cookie lišta a měření',
                    'body' => 'Lišta s tlačítkem pro odmítnutí, Google Analytics až po souhlasu, pryč se starým Universal Analytics.',
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
