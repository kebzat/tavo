<?php

use App\Enums\ChecklistItemStatus;
use App\Enums\ChecklistPriority;
use App\Models\Audit;
use App\Models\Client;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Audit a checklist Světa Cejlonu srovnané s oficiálními pravidly Googlu,
 * Bingu a Seznamu (ověřeno 25. 9. 2026):
 *
 * - Google Business Profile čistý e-shop mít nemůže, jen s výdejním místem.
 * - Filtry: nejdřív noindex, pak zákaz v robots.txt (Google u filtrů
 *   doporučuje robots.txt; noindex za zákazem by neviděl).
 * - Počet a pořadí nadpisů Google neřeší, adresy s překlepem neměníme
 *   (slova v URL mají zanedbatelný vliv).
 * - Nově: název webu „Marek Bezdíček“ v datech, doprava a vrácení zboží
 *   na úrovni firmy, IndexNow a citace v Copilotu (Bing).
 *
 * Audit se přepíše, jen když je beze změny od minulé migrace. Položky
 * checklistu se mění, jen když mají pořád původní text.
 */
return new class extends Migration
{
    private const AUDIT_TOKEN = 'kb3mcK5MHcNnlO5IcZzBtc4zkjmyfbQq0Tsz05uT';

    /** Otisk textu auditu po migraci 2026_09_25_230000. */
    private const OLD_BODY_SHA1 = '6dde23c19da2c30b652d04a957101633269bc7aa';

    private const CLIENT_SLUG = 'svet-cejlonu';

    public function up(): void
    {
        $audit = Audit::where('public_token', self::AUDIT_TOKEN)->first();

        if ($audit && sha1((string) $audit->body) === self::OLD_BODY_SHA1) {
            $audit->update(['body' => file_get_contents(database_path('content/audits/svet-cejlonu-2026-09-24.md'))]);
        }

        $client = Client::where('slug', self::CLIENT_SLUG)->first();

        if (! $client) {
            return;
        }

        $checklistIds = $client->checklists()->pluck('id');

        foreach ($this->changes() as [$title, $oldDescription, $values]) {
            DB::table('checklist_items')
                ->whereIn('checklist_id', $checklistIds)
                ->where('title', $title)
                ->where('description', $oldDescription)
                ->update([...$values, 'updated_at' => now()]);
        }

        foreach ($this->newItems() as [$sectionTitle, $title, $priority, $description]) {
            $section = DB::table('checklist_sections')
                ->whereIn('checklist_category_id', DB::table('checklist_categories')->whereIn('checklist_id', $checklistIds)->select('id'))
                ->where('title', $sectionTitle)
                ->first();

            if (! $section || DB::table('checklist_items')->where('checklist_section_id', $section->id)->where('title', $title)->exists()) {
                continue;
            }

            $checklistId = DB::table('checklist_categories')->where('id', $section->checklist_category_id)->value('checklist_id');

            DB::table('checklist_items')->insert([
                'checklist_section_id' => $section->id,
                'checklist_id' => $checklistId,
                'title' => $title,
                'description' => $description,
                'priority' => $priority->value,
                'status' => ChecklistItemStatus::Todo->value,
                'order_column' => (int) DB::table('checklist_items')->where('checklist_section_id', $section->id)->max('order_column') + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Obsah se od téhle chvíle ladí v administraci, předchozí znění je v historii gitu.
    }

    /**
     * [název položky, původní vysvětlivka, nové hodnoty]
     *
     * @return list<array{0: string, 1: string, 2: array<string, string>}>
     */
    private function changes(): array
    {
        return [
            [
                'Filtrační stránky označit jako „neindexovat“',
                'Jde o 1 786 adres jako /caje/p-tip/s-citronem-zvyrazni-svezest-nalevu, které vznikly po hromadném importu parametrů produktů. Po úpravě zmizí z indexu i ze sitemapy.',
                [
                    'title' => 'Filtrační stránky vyřadit z indexu a zakázat robotům',
                    'description' => 'Jde o 1 786 adres jako /caje/p-tip/s-citronem-zvyrazni-svezest-nalevu, které vznikly po hromadném importu parametrů produktů. Nejdřív je vyřadíme z indexu i ze sitemapy. Až z výsledků zmizí, zakážeme je robotům v robots.txt, jak u filtrů doporučuje Google.',
                ],
            ],
            [
                'Opravit vícenásobné hlavní nadpisy v popisech',
                'Stránka má mít jeden hlavní nadpis (H1). Ayush pleťový krém jich má pět, včetně řádků z podtržítek. Totéž u Kardamonu, Navratny a na stránce O nás.',
                [
                    'priority' => ChecklistPriority::Nice->value,
                    'description' => 'Ayush pleťový krém má pět hlavních nadpisů (H1), včetně řádků z podtržítek. Totéž u Kardamonu, Navratny a na stránce O nás. Google podle vlastních slov počet nadpisů neřeší, jde o čitelnost a přístupnost.',
                ],
            ],
            [
                'Opravit nesedící adresy produktů',
                '/p/lotovovy-kvet místo lotosový, /p/cerny-caj-earl-gray místo Grey a /p/mangostan-zeleny-caj, přitom jde o černý čaj. Adresu jde měnit jen s přesměrováním ze staré, jinak se ztratí, co o ní Google ví.',
                [
                    'status' => ChecklistItemStatus::Skipped->value,
                    'description' => '/p/lotovovy-kvet místo lotosový, /p/cerny-caj-earl-gray místo Grey a /p/mangostan-zeleny-caj, přitom jde o černý čaj. Adresy necháme: slova v adrese mají podle Googlu na pozice zanedbatelný vliv a změna by přinesla zbytečné riziko. Opravíme jen názvy.',
                ],
            ],
            [
                'Opravit chybné údaje o firmě ve strukturovaných datech',
                'Dnes se v nich firma jmenuje „Marek Bezdíček“ a cenová hladina je „$$$$$$“.',
                [
                    'title' => 'Opravit název webu a údaje o firmě ve strukturovaných datech',
                    'description' => 'Dnes se v nich firma jmenuje „Marek Bezdíček“ a cenová hladina je „$$$$$$“. Stejné jméno má v datech i celý web, takže ho Google může ukazovat nad výsledky místo „Svět Cejlonu“.',
                ],
            ],
            [
                'Ověřit nebo založit Google Business Profile',
                'Nejdřív ověřit, jestli profil už neexistuje. Pokud ne, založit jako firmu bez provozovny s působností v celé ČR. Recenze odtamtud vidí Google i Gemini.',
                [
                    'title' => 'Google Business Profile, jen pokud máte výdejní místo',
                    'priority' => ChecklistPriority::Nice->value,
                    'description' => 'Google profil dovolí jen firmě s místem, kam zákazníci chodí, nebo která za nimi jezdí. Čistý e-shop nárok nemá. Pokud máte výdejní místo nebo prodejnu, profil založíme a recenze odtamtud uvidí Google i Gemini.',
                ],
            ],
            [
                'Po 4 až 6 týdnech zkontrolovat, co se změnilo',
                'V Search Console projdeme, kolik filtrů a variant Google vyřadil, v Analytics návštěvy z AI a zopakujeme testovací dotazy v AI vyhledávání. Pošleme krátký report.',
                [
                    'description' => 'V Search Console projdeme, kolik filtrů a variant Google vyřadil, v Analytics návštěvy z AI, v Bing Webmaster Tools citace v Copilotu a zopakujeme testovací dotazy v AI vyhledávání. Pošleme krátký report.',
                ],
            ],
        ];
    }

    /**
     * [sekce, název, priorita, vysvětlivka]
     *
     * @return list<array{0: string, 1: string, 2: ChecklistPriority, 3: string}>
     */
    private function newItems(): array
    {
        return [
            [
                'Úpravy šablony',
                'Doplnit dopravu a vrácení zboží do strukturovaných dat',
                ChecklistPriority::Should,
                'Google je u e-shopů doporučuje uvést jednou za celou firmu. Může je pak ukazovat přímo u produktů ve výsledcích hledání.',
            ],
            [
                'AI roboti a podpora Upgates',
                'Zapnout IndexNow, pokud ho Upgates umí',
                ChecklistPriority::Nice,
                'IndexNow hned oznámí Bingu a dalším vyhledávačům každou změnu na webu. Bing podle něj drží aktuální i odpovědi Copilota.',
            ],
        ];
    }
};
