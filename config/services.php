<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    /*
     * Cloudflare Turnstile — ochrana poptávkového formuláře proti robotům.
     * Bez vyplněných klíčů se widget nevykreslí a nic se neověřuje,
     * viz App\Rules\Turnstile.
     */
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret' => env('TURNSTILE_SECRET_KEY'),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Úsudek nad prospekty v CRM a shrnutí auditu. Bez klíče se proklepnutí
    // webu obejde jen s měřením, viz App\Support\Crm\Ai\ProspectAi.
    'anthropic' => [
        // Vypínač všech placených volání z CRM (podrobný audit, úsudek při
        // proklepnutí, hledání firem). Vypnuto, dokud ho Tom výslovně nezapne:
        // audity se píšou v Claude Code na předplatném.
        'enabled' => (bool) env('ANTHROPIC_ENABLED', false),
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5'),
        // Jen u klíče, který nepatří žádnému workspace. ID je v konzoli
        // Anthropic v nastavení workspace (wrkspc_…).
        'workspace_id' => env('ANTHROPIC_WORKSPACE_ID'),
    ],

    // Google PageSpeed při proklepnutí webu. Klíč je nepovinný, bez něj
    // má Google nízký limit a měření občas selže. Audit se pak obejde bez něj.
    'pagespeed' => [
        'enabled' => env('PAGESPEED_ENABLED', true),
        'key' => env('PAGESPEED_API_KEY'),
    ],

    // Claude Code v kontejneru taveo-agent na stejném serveru (docs/AGENT.md).
    // API běží jen na 127.0.0.1, terminál z prohlížeče jde přes LiteSpeed
    // na /agent-terminal/. Bez tajemství se stránka Agent v nástrojích jen omluví.
    'agent' => [
        'url' => env('AGENT_URL', 'http://127.0.0.1:7681'),
        'secret' => env('AGENT_SECRET'),
        // Prázdné = wss:// na adrese webu. Lokálně přes SSH tunel ws://127.0.0.1:7681/.
        'ws_url' => env('AGENT_WS_URL'),
        // Token, kterým agent v kontejneru volá /nastroje/api/agent (čtení dat
        // klientů, zápis konceptů). Jiný než AGENT_SECRET i CRM_IMPORT_TOKEN.
        'api_token' => env('AGENT_API_TOKEN'),
    ],

    // ClickUp: komentář „@agent …“ u úkolu spustí agenta v projektu klienta
    // (docs/AGENT.md, ClickUp). Bez tokenu a tajemství webhooku je vypnuté.
    'clickup' => [
        // Osobní API token (pk_…). Agent jím čte úkol a odpovídá do komentářů.
        'token' => env('CLICKUP_API_TOKEN'),
        // Tajemství webhooku, ClickUp ho vrátí při založení (php artisan clickup:napojit).
        'webhook_secret' => env('CLICKUP_WEBHOOK_SECRET'),
        'team_id' => env('CLICKUP_TEAM_ID'),
        // Kdo smí agenta spustit: ID uživatelů ClickUpu oddělená čárkou (Tom, Pavel).
        'users' => array_filter(explode(',', (string) env('CLICKUP_AGENT_USERS', ''))),
        // Prostory bez přiřazeného klienta: ID prostoru:projekt agenta, oddělené čárkou.
        // Pavlovi klienti jsou v ClickUpu, v nástrojích zatím ne, jdou do projektu marketing.
        'space_projects' => collect(explode(',', (string) env('CLICKUP_SPACE_PROJECTS', '')))
            ->filter(fn (string $pair): bool => str_contains($pair, ':'))
            ->mapWithKeys(fn (string $pair): array => [trim(strstr($pair, ':', true)) => trim(substr(strstr($pair, ':'), 1))])
            ->all(),
        // Pojistka předplatného Claude: nejvýš tolik úloh z ClickUpu za hodinu a za den.
        'hourly_limit' => (int) env('CLICKUP_AGENT_HOURLY_LIMIT', 6),
        'daily_limit' => (int) env('CLICKUP_AGENT_DAILY_LIMIT', 30),
    ],

];
