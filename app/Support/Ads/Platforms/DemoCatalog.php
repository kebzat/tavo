<?php

namespace App\Support\Ads\Platforms;

use App\Enums\Ads\PrimaryGoal;
use Carbon\CarbonImmutable;

/**
 * Vymyšlení klienti pro ukázková data. Každý ukazuje jinou situaci, ať je
 * v přehledu vidět, jak nástroj reaguje: rozbité měření, okoukané reklamy,
 * přečerpaný rozpočet, klient, u kterého je všechno v pořádku.
 *
 * Čísla se počítají z hashe (účet, kampaň, den), takže pro daný den vyjdou
 * pokaždé stejně a denní synchronizace je jen doplní.
 */
final class DemoCatalog
{
    /**
     * Ukázkoví klienti. `accounts` jsou reklamní účty (klíč = external_id),
     * `ga4` property analytiky. Účty v SPARE_* nejsou u nikoho, dají se
     * zkusmo propojit přes „Přidat klienta“.
     */
    public const CLIENTS = [
        'Ukázka: Pražírna Zrnko' => [
            'website' => 'https://prazirna-zrnko.example',
            'goal' => PrimaryGoal::Purchases,
            'settings' => ['monthly_budget' => 18000, 'target_cpa' => 220, 'target_roas' => 4.5, 'fee_czk' => 3000, 'included_hours' => 2, 'hourly_rate' => 1200],
            'accounts' => [
                'demo_zrnko_meta' => ['name' => 'Pražírna Zrnko (Meta)', 'campaigns' => [
                    ['name' => 'Prospecting · výběrová káva', 'spend' => 260, 'cpm' => 90, 'ctr' => 1.5, 'cr' => 2.6, 'aov' => 920],
                    ['name' => 'Remarketing · košík a produkty', 'spend' => 110, 'cpm' => 150, 'ctr' => 2.3, 'cr' => 6.0, 'aov' => 980],
                ]],
                'demo_zrnko_google' => ['name' => 'Pražírna Zrnko (Google Ads)', 'campaigns' => [
                    ['name' => 'Search · značka', 'spend' => 60, 'cpm' => 900, 'ctr' => 9.0, 'cr' => 8.0, 'aov' => 950],
                    ['name' => 'Performance Max · celý katalog', 'spend' => 160, 'cpm' => 120, 'ctr' => 1.2, 'cr' => 2.4, 'aov' => 870],
                ]],
            ],
            'ga4' => ['demo_zrnko_ga4' => ['name' => 'prazirna-zrnko.example (GA4)', 'sessions' => 520, 'cr' => 1.9, 'aov' => 900]],
        ],
        'Ukázka: Čajovna Lístek' => [
            'website' => 'https://cajovna-listek.example',
            'goal' => PrimaryGoal::Purchases,
            'settings' => ['monthly_budget' => 12000, 'target_cpa' => 180, 'target_roas' => 4, 'fee_czk' => 3000, 'included_hours' => 2, 'hourly_rate' => 1200],
            'accounts' => [
                // První kampaň posledních sedm dní ztrácí CTR: upozornění na okoukané reklamy.
                'demo_listek_meta' => ['name' => 'Čajovna Lístek (Meta)', 'fatigue' => true, 'campaigns' => [
                    ['name' => 'Prospecting · zelené čaje', 'spend' => 220, 'cpm' => 95, 'ctr' => 1.4, 'cr' => 2.2, 'aov' => 780],
                    ['name' => 'Remarketing · košík', 'spend' => 90, 'cpm' => 140, 'ctr' => 2.1, 'cr' => 5.5, 'aov' => 860],
                    ['name' => 'Advantage+ · katalog', 'spend' => 130, 'cpm' => 80, 'ctr' => 1.1, 'cr' => 1.8, 'aov' => 690],
                ]],
            ],
            'ga4' => ['demo_listek_ga4' => ['name' => 'cajovna-listek.example (GA4)', 'sessions' => 380, 'cr' => 1.6, 'aov' => 760]],
        ],
        'Ukázka: Přírodní kosmetika Levandule' => [
            'website' => 'https://levandule.example',
            'goal' => PrimaryGoal::Purchases,
            'settings' => ['monthly_budget' => 9000, 'target_cpa' => 200, 'target_roas' => 3.5, 'fee_czk' => 3000, 'included_hours' => 2, 'hourly_rate' => 1200],
            'accounts' => [
                // Poslední tři dny žádný nákup, útrata běží: upozornění na rozbité měření.
                'demo_levandule_meta' => ['name' => 'Levandule (Meta)', 'broken_tracking' => true, 'campaigns' => [
                    ['name' => 'Prospecting · sérum a krémy', 'spend' => 190, 'cpm' => 85, 'ctr' => 1.3, 'cr' => 2.4, 'aov' => 640],
                    ['name' => 'Remarketing · návštěvníci webu', 'spend' => 80, 'cpm' => 130, 'ctr' => 2.0, 'cr' => 5.0, 'aov' => 690],
                ]],
            ],
            'ga4' => [],
        ],
        'Ukázka: Truhlářství Dub' => [
            'website' => 'https://truhlarstvi-dub.example',
            'goal' => PrimaryGoal::Leads,
            'settings' => ['monthly_budget' => 5000, 'target_cpa' => 250, 'fee_czk' => 3000, 'included_hours' => 1.5, 'hourly_rate' => 1200],
            'accounts' => [
                'demo_dub_meta' => ['name' => 'Truhlářství Dub (Meta)', 'campaigns' => [
                    ['name' => 'Poptávky · kuchyně na míru', 'spend' => 120, 'cpm' => 70, 'ctr' => 1.2, 'cr' => 4.0, 'aov' => 0],
                    ['name' => 'Video · dílna', 'spend' => 45, 'cpm' => 35, 'ctr' => 0.6, 'cr' => 1.0, 'aov' => 0],
                ]],
            ],
            'ga4' => [],
        ],
        'Ukázka: Fitness Hradec' => [
            'website' => 'https://fitness-hradec.example',
            'goal' => PrimaryGoal::Leads,
            // Rozpočet nastavený níž, než kampaně utrácejí: upozornění na přečerpání.
            'settings' => ['monthly_budget' => 4000, 'target_cpa' => 150, 'fee_czk' => 3000, 'included_hours' => 2, 'hourly_rate' => 1200],
            'accounts' => [
                'demo_fitness_meta' => ['name' => 'Fitness Hradec (Meta)', 'campaigns' => [
                    ['name' => 'Leady · první trénink zdarma', 'spend' => 170, 'cpm' => 60, 'ctr' => 1.6, 'cr' => 6.0, 'aov' => 0],
                ]],
            ],
            'ga4' => [],
        ],
    ];

    /** Nepřiřazené účty, na kterých jde vyzkoušet propojení nového klienta. */
    public const SPARE_ACCOUNTS = [
        'demo_strana_meta' => ['name' => 'Knihkupectví Stránka (Meta)', 'campaigns' => [
            ['name' => 'Prospecting · novinky', 'spend' => 150, 'cpm' => 80, 'ctr' => 1.3, 'cr' => 2.0, 'aov' => 540],
            ['name' => 'Remarketing · košík', 'spend' => 60, 'cpm' => 120, 'ctr' => 2.2, 'cr' => 5.0, 'aov' => 560],
        ]],
        'demo_pivonka_meta' => ['name' => 'Květinářství Pivoňka (Meta)', 'campaigns' => [
            ['name' => 'Kytice s doručením · Hradec', 'spend' => 100, 'cpm' => 75, 'ctr' => 1.7, 'cr' => 3.0, 'aov' => 890],
        ]],
    ];

    public const SPARE_GA4 = [
        'demo_strana_ga4' => ['name' => 'knihkupectvi-stranka.example (GA4)', 'sessions' => 300, 'cr' => 1.4, 'aov' => 540],
    ];

    /**
     * Všechny reklamní účty z katalogu i s příznaky scénáře.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function adAccounts(): array
    {
        $accounts = self::SPARE_ACCOUNTS;

        foreach (self::CLIENTS as $client) {
            $accounts += $client['accounts'];
        }

        return $accounts;
    }

    /** @return array<string, array<string, mixed>> */
    public static function analyticsAccounts(): array
    {
        $accounts = self::SPARE_GA4;

        foreach (self::CLIENTS as $client) {
            $accounts += $client['ga4'];
        }

        return $accounts;
    }

    /** Pseudonáhodné číslo mezi −1 a 1, pro stejný klíč vždy stejné. */
    public static function noise(string $key): float
    {
        return (crc32($key) % 2001) / 1000 - 1;
    }

    /** Kolik dní před dneškem ten den je. Scénáře se vážou k poslednímu týdnu. */
    public static function daysAgo(string $date): int
    {
        return (int) CarbonImmutable::parse($date)->diffInDays(CarbonImmutable::today());
    }
}
