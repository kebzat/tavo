<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Výchozí šablony zpráv pro CRM.
 *
 * Patří sem, ne do seederu: `CrmSeeder` se pouští při zakládání instance,
 * takže na běžící web by se šablony přidané později nikdy nedostaly. Nasazení
 * spouští `migrate`, proto je přiveze datová migrace, stejně jako u obsahu
 * webu (viz docs/CONTENT-MODEL.md).
 *
 * Chová se jako `add()` u settings migrací: zakládá podle názvu, takže co si
 * v administraci upravíš, ti příští nasazení nepřepíše. Nová šablona se
 * v budoucnu přidá další migrací, ne úpravou téhle.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ($this->templates() as $template) {
            if (DB::table('crm_message_templates')->where('name', $template['name'])->exists()) {
                continue;
            }

            DB::table('crm_message_templates')->insert($template + [
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('crm_message_templates')
            ->whereIn('name', array_column($this->templates(), 'name'))
            ->delete();
    }

    /**
     * Zástupné texty ({{firma}}, {{jmeno}}, {{bolest}}, {{reference}}, {{web}},
     * {{mesto}}) dosazuje App\Support\Crm\TemplateRenderer až při použití.
     *
     * @return array<int, array{name: string, channel: string, subject: ?string, body: string}>
     */
    private function templates(): array
    {
        return [
            [
                'name' => 'Teplý kontakt: bývalý klient nebo agentura',
                'channel' => 'email',
                'subject' => 'Volná kapacita na podzim',
                'body' => <<<'TEXT'
                Dobrý den {{jmeno}},
                v nejbližších týdnech mi vychází volná kapacita. Dělám hlavně Shoptet, WooCommerce a Upgates: migrace, napojení Pohody a dopravců, měření.
                S Pavlem k tomu umíme přidat i kampaně na Meta, když je potřeba web i přivést návštěvníky.
                Nepotřebujete teď něco vy, nebo někdo, koho znáte?

                Tom, Taveo
                TEXT,
            ],
            [
                'name' => 'Lokální firma: studený e-mail s postřehem',
                'channel' => 'email',
                'subject' => '{{web}} a jedna věc, která mi tam chybí',
                'body' => <<<'TEXT'
                Dobrý den {{jmeno}},
                koukal jsem na {{web}} a všiml jsem si, že {{bolest}}.
                Dělali jsme web pro {{reference}}, tohle je přesně ten typ věci, kterou tam řešíme.
                Máte patnáct minut na telefon? Řeknu, co bych řešil jako první a co to stojí. Když zjistím, že vám to nepomůže, řeknu i to.

                Tom, Taveo
                TEXT,
            ],
            [
                'name' => 'Odpověď na poptávku',
                'channel' => 'demand_reply',
                'subject' => 'Reakce na poptávku',
                'body' => <<<'TEXT'
                Dobrý den,
                {{bolest}} je problém, který jsem řešil několikrát, naposledy u {{reference}}.
                Orientačně to vidím na [cena] a zvládnu to do [termín]. Přesně to řeknu, až uvidím, co máte na pozadí.
                Zavolal bych si patnáct minut. Hodí se vám zítra dopoledne?

                Tom, Taveo
                TEXT,
            ],
            [
                'name' => 'Follow-up, den 3 až 7',
                'channel' => 'email',
                'subject' => 'Ještě jednou k {{firma}}',
                'body' => <<<'TEXT'
                Dobrý den {{jmeno}},
                jen připomínám svůj e-mail z minulého týdne, ať nezapadl.
                Stačí i krátké „ne, díky", pak už se ozývat nebudu.
                Ve čtvrtek odpoledne mám volno, kdyby se vám chtělo telefonovat.

                Tom, Taveo
                TEXT,
            ],
            [
                'name' => 'Agentura: subdodávka',
                'channel' => 'email',
                'subject' => 'Kapacita na Shoptet, WooCommerce a Laravel',
                'body' => <<<'TEXT'
                Dobrý den {{jmeno}},
                mám volnou kapacitu na vývoj pro Shoptet, WooCommerce a Laravel, k tomu měření a napojení Pohody.
                Fungovalo by to jako subdodávka: pevná cena na zadání, komunikace přes vás, ke klientovi nechodím.
                Reference i konkrétní čísla z posledních zakázek pošlu na vyžádání.

                Tom, Taveo
                TEXT,
            ],
            [
                'name' => 'Osnova telefonu po e-mailu',
                'channel' => 'call_script',
                'subject' => null,
                'body' => <<<'TEXT'
                1. Kdo volá a proč: Tom z Taveo, posílal jsem e-mail k {{web}}.
                2. Otázka místo prezentace: řešíte {{bolest}} teď nějak, nebo to zatím leží?
                3. Jedna konkrétní věc, kterou bych udělal první, a proč zrovna tu.
                4. Cena a termín orientačně, rovnou. Když se do rozpočtu nevejdeme, ať to víme hned.
                5. Konec s dohodou: pošlu shrnutí do e-mailu a ozvu se v [den].
                TEXT,
            ],
        ];
    }
};
