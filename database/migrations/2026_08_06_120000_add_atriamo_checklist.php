<?php

use App\Enums\ChecklistItemStatus;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Client;
use Illuminate\Database\Migrations\Migration;

/**
 * Checklist pro ATRIAMO: klon univerzální šablony doplněný o zjištění
 * z průchodu prototypem na atriamo.cz 6. 8. 2026.
 *
 * Proč migrace a ne seeder: nasazení pouští `migrate --force`, ale `db:seed`
 * jen při úplně prvním nasazení. Obsah, který má dorazit na běžící web,
 * proto jezdí migrací, viz docs/CONTENT-MODEL.md.
 *
 * Chová se jako `add()` u settings migrací: založí, jen když klient ještě
 * neexistuje. Jakmile se checklist začne upravovat v administraci, migrace
 * se ho nedotkne. Poznámky zestárnou ve chvíli, kdy se prototyp přepíše,
 * a od té chvíle patří upravovat v administraci, ne tady.
 *
 * Klonování schválně nechávám na modelu místo ručních INSERTů. Duplikovat
 * logiku duplicateFor() do SQL by znamenalo udržovat ji na dvou místech.
 */
return new class extends Migration
{
    private const CLIENT_SLUG = 'atriamo';

    /**
     * Klíč = část názvu položky, podle které se řádek najde.
     * Hodnota = [stav, interní poznámka].
     *
     * @var array<string, array{0: ChecklistItemStatus, 1: string}>
     */
    private const FINDINGS = [
        'Obsah se vykresluje na serveru' => [
            ChecklistItemStatus::Todo,
            'Odpověď ze serveru má 1,1 kB a obsahuje prázdný <div id="root">. Celý web dogeneruje React až v prohlížeči. Tohle je největší zásah, který prototyp čeká.',
        ],
        'Web i kalkulačka běží na jedné doméně' => [
            ChecklistItemStatus::Done,
            'Kalkulačka běží na /kalkulacka pod stejnou doménou. V pořádku, jen to takhle udržet.',
        ],
        'Leady a rozpracované kalkulace se ukládají do databáze' => [
            ChecklistItemStatus::Todo,
            'Zjistit u Martina, kam odeslaná kalkulace putuje. Pokud jen do e-mailu, chybí evidence, filtrování i podklad pro vyhodnocení. Nabízí se Laravel a Filament, na kterém běží tenhle web, včetně stavů zakázky.',
        ],
        'Každý krok kalkulačky má vlastní adresu' => [
            ChecklistItemStatus::Todo,
            'Ověřeno klikáním: kalkulačka má 10 kroků a všechny běží na /kalkulacka, adresa se nemění. Bez vlastních adres nepoznáme, na kterém z deseti kroků lidé odcházejí. U desetikrokové kalkulačky je to ta nejcennější informace, jakou můžeme mít.',
        ],
        'Děkovací stránka má vlastní adresu' => [
            ChecklistItemStatus::Todo,
            'Nenalezena. Server vrací tutéž aplikaci na každou adresu, takže se nedá poznat, jestli /dekujeme existuje.',
        ],
        'Chybová stránka 404 nabízí cestu zpět' => [
            ChecklistItemStatus::Todo,
            'Neexistující adresa vrací HTTP 200 a prázdnou aplikaci. Vyhledávač si takovou stránku zaindexuje jako platnou.',
        ],
        'Právní stránky jsou na webu' => [
            ChecklistItemStatus::Todo,
            'V patičce je text „Ochrana osobních údajů · Obchodní podmínky · Cookies", ale nejsou to odkazy a stránky za nimi nevedou nikam.',
        ],
        'robots.txt a sitemap.xml' => [
            ChecklistItemStatus::Todo,
            'Ani jedno neexistuje. /robots.txt i /sitemap.xml vrací tutéž aplikaci jako homepage.',
        ],
        'Obrázek pro sdílení a favicona' => [
            ChecklistItemStatus::Todo,
            'og:title a og:description jsou vyplněné, og:image chybí a favicona taky. Odkaz sdílený na Facebooku vypadá jako prázdný obdélník, což je u reklamy škoda.',
        ],
        'Kanonické adresy' => [
            ChecklistItemStatus::Todo,
            'Značka canonical na stránce není.',
        ],
        'Každá adresa má vlastní title a popisek' => [
            ChecklistItemStatus::InProgress,
            'Homepage i kalkulačka mají vlastní title, ale mění ho až JavaScript. Do statické odpovědi ze serveru se dostane jen ten první.',
        ],
        'Cookie lišta nabízí odmítnutí stejně viditelně jako souhlas' => [
            ChecklistItemStatus::Todo,
            'Žádná lišta na webu není. Web zatím nic neměří, takže se zatím nic neporušuje, ale s prvním pixelem to začne.',
        ],
        'Zásady zpracování osobních údajů odpovídají skutečnosti' => [
            ChecklistItemStatus::Todo,
            'Kalkulačka sbírá kontaktní údaje, takže tohle musí být hotové dřív než první reklama.',
        ],
        'Google Tag Manager jako jediné místo, kde se spravují skripty' => [
            ChecklistItemStatus::Todo,
            'Na webu není GTM, gtag ani Meta Pixel. Měření se staví od nuly, což je vlastně dobrá zpráva: dá se udělat rovnou pořádně.',
        ],
        'dataLayer je na stránce dřív než GTM' => [
            ChecklistItemStatus::Todo,
            'window.dataLayer je undefined.',
        ],
        'Rozpracovaná kalkulace se průběžně ukládá na server' => [
            ChecklistItemStatus::Todo,
            'Po přechodu na druhý krok zůstávají localStorage, sessionStorage i cookies prázdné. Obnovení stránky uprostřed desetikrokové kalkulačky tedy smaže všechno zadané.',
        ],
        'Ukazatel průběhu' => [
            ChecklistItemStatus::Done,
            'Kalkulačka ukazuje „Krok 1 z 10" a lištu průběhu. Tohle je hotové.',
        ],
        'Kontakt se získává co nejdřív' => [
            ChecklistItemStatus::Todo,
            'Projít pořadí kroků. U deseti kroků se vyplatí mít e-mail dřív než na konci, jinak o většinu rozdělaných kalkulací přijdeme.',
        ],
        'Ochrana proti botům' => [
            ChecklistItemStatus::Todo,
            'Formulář nemá žádnou ochranu. Doporučuju Cloudflare Turnstile, je zdarma a na rozdíl od reCAPTCHy neobtěžuje zákazníka.',
        ],
        'Odkaz na obnovení kalkulace pro zákazníka i obchodníka' => [
            ChecklistItemStatus::Todo,
            'U desetikrokové kalkulačky za statisíce dává velký smysl. Zákazník naváže, kde skončil, a obchodník otevře tutéž kalkulaci a projde ji s ním po telefonu.',
        ],
        'Kontakt s adresou, IČO a jménem člověka' => [
            ChecklistItemStatus::Todo,
            'V patičce je zástupný telefon +420 000 000 000. Před spuštěním reklam vyměnit, jinak zaplatíme za kliknutí a zákazník se nedovolá.',
        ],
        'Hlášení chyb aplikace s upozorněním' => [
            ChecklistItemStatus::Todo,
            'Zatím nic. Až poběží reklamy, potřebujeme vědět o rozbité kalkulačce dřív než z reklamace.',
        ],
        'Denní záloha databáze mimo produkční server' => [
            ChecklistItemStatus::Todo,
            'Vyřešit spolu s databází leadů. Každá ztracená zakázka je zaplacená z reklamního rozpočtu.',
        ],
        'Fotky skutečných realizací' => [
            ChecklistItemStatus::Todo,
            'Domluvit s Jardou fotky z realizací Katastry. Značka je nová, ale řemeslo za ní staré, a to se dá ukázat férově.',
        ],
        'Je vidět, kdo za firmou stojí' => [
            ChecklistItemStatus::Todo,
            'Komunikovat zkušenost realizačního partnera, ne desítky vlastních realizací, které ATRIAMO zatím nemá.',
        ],
    ];

    public function up(): void
    {
        $template = Checklist::templates()->first();

        if (! $template || Client::where('slug', self::CLIENT_SLUG)->exists()) {
            return;
        }

        $client = Client::create([
            'name' => 'ATRIAMO',
            'slug' => self::CLIENT_SLUG,
            'website_url' => 'https://atriamo.cz',
            'note' => 'Bioklimatické pergoly. Nová značka, realizačním partnerem je Katastra (Jarda). Marketing Pavel Včeliš, koordinace Jan.',
        ]);

        $checklist = $template->duplicateFor($client, 'Technický checklist webu atriamo.cz');

        $checklist->update([
            'intro' => 'Průchod prototypu atriamo.cz z 6. 8. 2026 proti našemu technickému checklistu. '
                .'Web bereme jako nástřel, který ověřil směr. Tenhle seznam říká, co na něm dodělat, '
                .'aby se dal měřit a aby se do něj daly pustit reklamy.',
        ]);

        foreach (self::FINDINGS as $needle => [$status, $note]) {
            ChecklistItem::query()
                ->where('checklist_id', $checklist->getKey())
                ->where('title', 'like', '%'.$needle.'%')
                ->first()
                ?->update(['status' => $status, 'internal_note' => $note]);
        }
    }

    public function down(): void
    {
        $client = Client::where('slug', self::CLIENT_SLUG)->first();

        if (! $client) {
            return;
        }

        // Vazba checklistu na klienta je nullOnDelete, takže smazat rovnou
        // klienta by po sobě nechalo checklist bez majitele.
        $client->checklists()->each(fn (Checklist $checklist) => $checklist->delete());
        $client->delete();
    }
};
