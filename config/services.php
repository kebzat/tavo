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

];
