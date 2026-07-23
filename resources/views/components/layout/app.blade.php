@props([
    'title' => null,
    'description' => null,
    'ogImage' => null,
    'bodyClass' => '',
    'schema' => [],
])

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <x-seo.meta :title="$title" :description="$description" :ogImage="$ogImage" :schema="$schema" />

    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="{{ $bodyClass }}">
    <a href="#obsah"
       class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-[100] focus:rounded-pill focus:bg-ink focus:px-5 focus:py-3 focus:text-sm focus:font-bold focus:text-cream">
        Přeskočit na obsah
    </a>

    <x-layout.nav />

    <main id="obsah">
        {{ $slot }}
    </main>

    <x-layout.footer />
    <x-cookie-bar />
</body>
</html>
