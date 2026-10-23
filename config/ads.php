<?php

/*
|--------------------------------------------------------------------------
| Reklamy klientů (panel /nastroje → Reklamy)
|--------------------------------------------------------------------------
| Přístupy k reklamním systémům. Jsou jen v .env, do databáze nepatří:
| klienti nám účty nasdílejí jako partnerovi (Business Manager Taveo,
| MCC Taveo) a čteme je jedním tokenem Taveo, ne tokeny klientů.
|
| Prahy upozornění a příjemci souhrnu se mění z prohlížeče, bydlí
| v App\Settings\AdsSettings. Podrobnosti v docs/ADS.md.
*/

return [

    'meta' => [
        /*
         * Token systémového uživatele z Business Manageru Taveo s oprávněním
         * ads_read. Nevyprší. Prázdný token Meta vypne, synchronizace ji přeskočí.
         */
        'token' => env('META_SYSTEM_USER_TOKEN'),

        /*
         * Tajný klíč aplikace. Když je vyplněný, posílá se s každým voláním
         * appsecret_proof a ukradený token bez něj nic nezmůže.
         */
        'app_secret' => env('META_APP_SECRET'),

        'api_version' => env('META_API_VERSION', 'v25.0'),

        'base_url' => 'https://graph.facebook.com',

        /*
         * Pojistka: nejvýš tolik dotazů na Metu za den (App\Support\Ads\Platforms\ApiGuard).
         * Na Metu sahá jen ads:sync: ráno 1 dotaz na seznam účtů, 6 na účet
         * a 1 na skupinu kampaní, 20 klientů se dvěma skupinami = 161 dotazů.
         * Ruční obnova (--requested) přidá 6–8 dotazů na účet, nejvýš jednou za 2 h.
         * Strop je jen pro případ chyby, běžně se k němu nepřiblížíme.
         */
        'daily_call_limit' => (int) env('META_DAILY_CALL_LIMIT', 300),
    ],

    'google_ads' => [
        /*
         * OAuth klient z Google Cloud projektu Taveo a refresh token uživatele,
         * který má přístup k manažerskému účtu (MCC) Taveo. Klientské účty
         * se propojují pod MCC.
         */
        'client_id' => env('GOOGLE_ADS_CLIENT_ID'),
        'client_secret' => env('GOOGLE_ADS_CLIENT_SECRET'),
        'refresh_token' => env('GOOGLE_ADS_REFRESH_TOKEN'),

        /* Číslo MCC Taveo (123-456-7890). Posílá se jako login-customer-id. */
        'login_customer_id' => env('GOOGLE_ADS_LOGIN_CUSTOMER_ID'),

        /*
         * Od 10. 9. 2026 řídí úroveň přístupu Cloud projekt. Developer token
         * se posílá, jen když je vyplněný (starší nastavení ho stále chtějí).
         */
        'developer_token' => env('GOOGLE_ADS_DEVELOPER_TOKEN'),

        'api_version' => env('GOOGLE_ADS_API_VERSION', 'v24'),

        'daily_call_limit' => (int) env('GOOGLE_ADS_DAILY_CALL_LIMIT', 200),
    ],

    'ga4' => [
        /*
         * Klíč servisního účtu Taveo: celý JSON, nebo cesta k souboru (relativně
         * ke kořeni projektu). Klient přidá e-mail servisního účtu do své GA4
         * property jako čtenáře.
         */
        'credentials' => env('GA4_CREDENTIALS'),

        'daily_call_limit' => (int) env('GA4_DAILY_CALL_LIMIT', 200),
    ],

    /*
     * Ukázková data: vymyšlení klienti a platforma, která čísla generuje.
     * Hodí se na vyzkoušení nástroje bez přístupů. Po napojení skutečných
     * účtů vypnout (ADS_DEMO=false) a klienty smazat: php artisan ads:demo --remove
     */
    'demo_enabled' => (bool) env('ADS_DEMO', true),

    /*
     * Kolik dní zpět se při denní synchronizaci přepisuje. Meta dopočítává
     * konverze zpětně podle atribučního okna, včerejšek proto není konečný.
     */
    'sync_days' => (int) env('ADS_SYNC_DAYS', 7),

    /*
     * Když ranní stažení pár dní neprošlo, další běh doplní i chybějící dny,
     * nejvýš tolik zpátky. 92 dní je u Mety pořád jeden dotaz (stahuje se po čtvrtletích).
     */
    'catch_up_days' => (int) env('ADS_CATCH_UP_DAYS', 92),

    /*
     * Ruční obnova čísel z detailu klienta (tlačítko Obnovit čísla). Odsud se
     * na platformu nesahá, žádost vyřídí `ads:sync --requested` (každých 5 minut).
     * Jeden účet jde obnovit nejvýš jednou za tolik minut.
     */
    'refresh_cooldown_minutes' => (int) env('ADS_REFRESH_COOLDOWN', 120),

    /*
     * Historie, kterou stáhne první ranní běh po připojení nového účtu, v měsících. Stahuje se
     * po čtvrtletích: 3 měsíce = 1 dotaz, 37 měsíců (maximum Mety) = asi 13 dotazů.
     * Delší historii zapneme až po domluvě s Pavlem kvůli zatížení API.
     */
    'backfill_months' => (int) env('ADS_BACKFILL_MONTHS', 3),

    /* Tlačítko „Doplnit starší historii“ na detailu klienta. Zatím skryté. */
    'history_backfill' => (bool) env('ADS_HISTORY_BACKFILL', false),

];
