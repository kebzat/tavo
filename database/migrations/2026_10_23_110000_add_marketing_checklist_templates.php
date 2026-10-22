<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Dvě šablony checklistů pro marketing podle dotazníku Pavla (22. 10. 2026):
 *
 * - Onboarding klienta: brief a cíle, rozsah, rozpočet, kontakty a schvalování,
 *   přístupy, obchodní data, měření, značka a kreativy, nabídka a landing page.
 * - Kontrola před spuštěním kampaně: cíl, rozpočet, termíny, lokalita, odkazy,
 *   UTM, texty, platnost nabídky, návaznost na landing page.
 *
 * Z šablony se v nástrojích (Checklisty) klonuje checklist pro klienta, u
 * položky se pak vede stav a interní poznámka „co chybí“. Kampaň spouští
 * a rozpočty mění dál Pavel ručně, checklist je kontrola, ne automat.
 *
 * Jako ostatní šablony: založí se, jen když ještě neexistuje. Další úpravy
 * patří do administrace, ne sem.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->templates() as $template) {
            if (DB::table('checklists')->where('is_template', true)->where('name', $template['name'])->exists()) {
                continue;
            }

            $this->create($template);
        }
    }

    public function down(): void
    {
        DB::table('checklists')->where('is_template', true)->whereIn('name', array_column($this->templates(), 'name'))->delete();
    }

    /** @param  array<string, mixed>  $template */
    private function create(array $template): void
    {
        $now = now();

        $checklistId = DB::table('checklists')->insertGetId([
            'client_id' => null,
            'is_template' => true,
            'is_public' => false,
            'name' => $template['name'],
            'slug' => $template['slug'],
            'intro' => $template['intro'],
            'public_token' => Str::random(40),
            'order_column' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($template['categories'] as $categoryOrder => $category) {
            $categoryId = DB::table('checklist_categories')->insertGetId([
                'checklist_id' => $checklistId,
                'title' => $category['title'],
                'slug' => Str::slug($category['title']),
                'description' => $category['description'],
                'order_column' => $categoryOrder + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $sectionId = DB::table('checklist_sections')->insertGetId([
                'checklist_category_id' => $categoryId,
                'title' => $category['title'],
                'description' => null,
                'order_column' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('checklist_items')->insert(collect($category['items'])->values()->map(fn (array $item, int $order): array => [
                'checklist_section_id' => $sectionId,
                'checklist_id' => $checklistId,
                'title' => $item[0],
                'description' => $item[1],
                'internal_note' => null,
                'priority' => $item[2],
                'status' => 'todo',
                'order_column' => $order + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        }
    }

    /**
     * Položka = [název, vysvětlivka, priorita must/should/nice].
     *
     * @return list<array<string, mixed>>
     */
    private function templates(): array
    {
        return [
            [
                'name' => 'Onboarding marketingového klienta',
                'slug' => 'sablona-onboarding-marketing',
                'intro' => 'Co musí být jasné a připravené, než se pustí první kampaň. U každého bodu veďte stav a do interní poznámky napište, kdo za něj odpovídá a co chybí.',
                'categories' => [
                    ['title' => 'Brief, cíle a rozsah', 'description' => 'Co klient chce a za co platí.', 'items' => [
                        ['Brief a hlavní cíl spolupráce', 'Prodej z e-shopu, leady, awareness, boosting, nebo kombinace. Zapsat do karty klienta.', 'must'],
                        ['KPI a jak je budeme měřit', 'Např. PNO/ROAS, cena za lead, dosah. U každého čísla zdroj pravdy (e-shop, CRM, reklamní systém).', 'must'],
                        ['Rozsah spolupráce', 'Co děláme my, co klient, co externista (obsah, grafika, video). Co paušál nezahrnuje.', 'must'],
                        ['Reklamní rozpočet odděleně od ceny správy', 'Měsíční rozpočet na reklamy a kdo ho platí. Cenu správy potvrzuje Pavel.', 'must'],
                        ['Sezona, marže a priority sortimentu', 'Kdy se prodává nejvíc, na čem klient vydělává, co nechce propagovat.', 'should'],
                    ]],
                    ['title' => 'Kontakty a schvalování', 'description' => 'Kdo rozhoduje a jak rychle.', 'items' => [
                        ['Kontaktní osoby a kdo co schvaluje', 'Texty, kreativy, rozpočet, akce. Zapsat do karty klienta.', 'must'],
                        ['Komunikační kanál a rytmus', 'E-mail, WhatsApp, Slack, schůzky. Jak často report (týden / měsíc) a komu.', 'must'],
                        ['Jak chce klient vyúčtování hodin', 'Do faktury, nebo e-mailem, jak podrobně. Zapsat do karty klienta → Vyúčtování hodin.', 'should'],
                    ]],
                    ['title' => 'Přístupy a data', 'description' => 'Bez přístupů nejde měřit ani spouštět.', 'items' => [
                        ['Reklamní účet Meta nasdílený Business Manageru Taveo', 'Klient přidá BM Taveo jako partnera a nasdílí reklamní účet. Pak ho jde propojit v Reklamy → Přidat klienta.', 'must'],
                        ['Stránka na Facebooku a Instagram účet v BM', 'Pro reklamy a boosting příspěvků.', 'must'],
                        ['Google Ads pod MCC Taveo (když se používá)', 'Klient přijme žádost o propojení.', 'should'],
                        ['GA4: servisní účet Taveo jako čtenář', 'E-mail servisního účtu je v Nastavení reklam.', 'should'],
                        ['Obchodní data: objednávky, tržby, leady', 'Odkud a jak často je dostaneme (export z e-shopu, CRM, tabulka). Bez osobních údajů zákazníků.', 'must'],
                        ['Zpracovatelská smlouva / dohoda o datech', 'Kam smíme data klienta dávat (včetně AI nástrojů). Bez ní do AI žádná osobní data.', 'must'],
                    ]],
                    ['title' => 'Měření', 'description' => 'Nejdřív spolehlivá data, pak optimalizace.', 'items' => [
                        ['Meta pixel a Conversions API s deduplikací', 'Nákup/lead se odesílá jednou, s hodnotou a měnou.', 'must'],
                        ['Konverze sedí s e-shopem / CRM', 'Porovnat týden: objednávky v e-shopu vs. GA4 vs. reklamní systémy.', 'must'],
                        ['UTM konvence pro kampaně', 'Jednotné utm_source, utm_medium, utm_campaign.', 'should'],
                        ['Cookie lišta a souhlas', 'Měření respektuje souhlas, víme, kolik dat chybí.', 'should'],
                    ]],
                    ['title' => 'Značka, kreativy a nabídka', 'description' => 'Z čeho budeme tvořit.', 'items' => [
                        ['Tón komunikace a zakázaná slova', 'Zapsat do karty klienta. Pokud brand manuál není, sepsat aspoň základ.', 'must'],
                        ['Podklady: loga, fotky, videa, UGC', 'Kde jsou a kdo dodává nové.', 'must'],
                        ['Nabídka a hlavní argumenty', 'Proč koupit teď a proč od klienta. Platnost akcí.', 'must'],
                        ['Landing page pro kampaň', 'Funguje na mobilu, má pixel, odpovídá reklamě. Zapsat do karty klienta → Cílové stránky.', 'must'],
                    ]],
                ],
            ],
            [
                'name' => 'Kontrola před spuštěním kampaně',
                'slug' => 'sablona-kontrola-kampane',
                'intro' => 'Projít před každým spuštěním nebo větší změnou kampaně. Kampaň nasazuje a rozpočty mění Pavel ručně, tohle je kontrola, aby se nic nezapomnělo.',
                'categories' => [
                    ['title' => 'Kampaň', 'description' => 'Nastavení ve Správci reklam.', 'items' => [
                        ['Cíl kampaně odpovídá cíli klienta', 'Prodej / leady / dosah / interakce. Optimalizační událost je ta správná.', 'must'],
                        ['Rozpočet a jeho rozložení', 'Denní nebo celoživotní, sedí s měsíčním plánem klienta.', 'must'],
                        ['Termíny', 'Začátek a konec, hlavně u akcí s platností.', 'must'],
                        ['Lokalita, věk a publikum', 'Sedí s briefem, vyloučení stávajících zákazníků, kde to dává smysl.', 'must'],
                        ['Umístění a formáty', 'Kreativy mají verze pro všechna zvolená umístění.', 'should'],
                    ]],
                    ['title' => 'Kreativy a texty', 'description' => 'Co lidé uvidí.', 'items' => [
                        ['Texty schválené klientem', 'Bez překlepů, v tónu značky, bez zakázaných slov.', 'must'],
                        ['Platnost nabídky v textu sedí', 'Cena, sleva, termín. Po skončení akce reklamu vypnout.', 'must'],
                        ['Zapsáno do kreativních testů', 'Co testujeme a proč (u klienta → Kreativní testy).', 'should'],
                    ]],
                    ['title' => 'Odkazy a měření', 'description' => 'Kam reklama vede a jestli to změříme.', 'items' => [
                        ['Cílová stránka funguje a odpovídá reklamě', 'Načte se na mobilu, produkt je skladem, akce na ní platí.', 'must'],
                        ['UTM parametry na odkazu', 'Podle konvence klienta.', 'must'],
                        ['Pixel a konverzní událost na cílové stránce', 'Ověřit Pixel Helperem nebo testem událostí.', 'must'],
                        ['Stránka zapsaná do kontroly cílových stránek', 'Karta klienta → Cílové stránky. Ranní kontrola pak hlásí výpadek.', 'should'],
                    ]],
                ],
            ],
        ];
    }
};
