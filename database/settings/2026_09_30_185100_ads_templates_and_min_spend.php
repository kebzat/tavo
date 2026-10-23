<?php

use App\Support\ContentSettingsMigration;

/**
 * Šablony čísel podle typu kampaní a minimální útrata pro upozornění
 * (zpětná vazba Pavla 23. 10. 2026, docs/ADS.md).
 *
 * - card_templates: šest čísel na kartě v přehledu klientů, zvlášť pro
 *   e-shop, leady, návštěvnost, boosting a nábor. Mění se třemi tečkami
 *   u čísla na kartě, platí pro všechny klienty a skupiny s tím cílem.
 * - tile_templates: dlaždice na detailu a v reportu, výchozí podle cíle.
 *   Klient i skupina si můžou vybrat vlastní.
 * - min_spend: pod touhle útratou za týden výkonová pravidla mlčí,
 *   u malých čísel by svítila každá náhoda.
 *
 * Datum je před migrací s ukázkovými klienty, která AdsSettings načítá
 * (bez těchhle polí by na čisté databázi spadla). Na běžícím webu se
 * doplní při nejbližším `migrate` jako každá jiná čekající migrace.
 */
return new class extends ContentSettingsMigration
{
    public function up(): void
    {
        $this->add([
            'ads.min_spend' => 500,
            'ads.card_templates' => [
                'purchases' => ['spend', 'conversions', 'cost_per_conversion', 'roas', 'ctr', 'cpm'],
                'leads' => ['spend', 'conversions', 'cost_per_conversion', 'form_leads', 'web_leads', 'ctr'],
                'traffic' => ['spend', 'conversions', 'cost_per_conversion', 'landing_page_views', 'ctr', 'cpm'],
                'awareness' => ['spend', 'reach', 'frequency', 'cpm', 'conversions', 'ctr_all'],
                'recruitment' => ['spend', 'conversions', 'cost_per_conversion', 'reach', 'ctr', 'cpm'],
            ],
            'ads.tile_templates' => [
                'purchases' => ['spend', 'conversions', 'cost_per_conversion', 'roas', 'purchase_value', 'link_clicks', 'ctr', 'cpm'],
                'leads' => ['spend', 'conversions', 'cost_per_conversion', 'form_leads', 'web_leads', 'conversion_rate', 'link_clicks', 'cpc', 'ctr', 'cpm'],
                'traffic' => ['spend', 'conversions', 'cost_per_conversion', 'landing_page_views', 'landing_page_view_rate', 'impressions', 'ctr', 'cpm'],
                'awareness' => ['spend', 'reach', 'frequency', 'cpm', 'conversions', 'cost_per_conversion', 'impressions', 'ctr_all'],
                'recruitment' => ['spend', 'conversions', 'cost_per_conversion', 'form_leads', 'web_leads', 'reach', 'link_clicks', 'cpc', 'ctr', 'cpm'],
            ],
        ]);
    }
};
