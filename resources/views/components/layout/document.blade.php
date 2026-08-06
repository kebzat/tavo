@props([
    'title' => null,
    'eyebrow' => null,
])

{{--
    Layout sdílených pracovních dokumentů (technický checklist klienta).

    Záměrně nepoužívá x-layout.app: ten vkládá navigaci webu, patičku, měřicí
    kód a cookie lištu. Tahle stránka nic neměří a není součástí prezentace,
    takže z ní zůstává jen to, co drží vzhled — Montserrat, designové tokeny
    a favicona.

    Direktiva @fonts je povinná. Bez ní se stránka vysází systémovým písmem
    a na vývojářském Macu si toho nikdo nevšimne, protože Montserrat tam bývá
    nainstalovaný lokálně.
--}}
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title }}</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#f4ede1">

    @fonts

    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/favicon.ico" sizes="32x32">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <a href="#obsah"
       class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-[100] focus:rounded-pill focus:bg-ink focus:px-5 focus:py-3 focus:text-sm focus:font-bold focus:text-cream">
        Přeskočit na obsah
    </a>

    <header class="section-x flex items-center justify-between gap-6 py-[18px]">
        <a href="{{ route('home') }}" class="flex items-center" aria-label="{{ $site->brand_name }} — úvodní stránka">
            <img src="/images/taveo-logo-dark.svg" alt="{{ $site->brand_name }}"
                 width="112" height="26" class="block h-[26px] w-auto">
        </a>

        @if ($eyebrow)
            <p class="text-sm font-semibold text-muted">{{ $eyebrow }}</p>
        @endif
    </header>

    <main id="obsah">
        {{ $slot }}
    </main>

    <footer class="section-x section-y-sm bg-brick text-cream" data-block-bg="brick">
        <div class="container-tavo flex flex-col gap-4 menu:flex-row menu:items-end menu:justify-between">
            <p class="max-w-[46ch] text-perex text-cream/80">
                Pracovní dokument. Průběžně ho aktualizujeme, takže se obsah může měnit.
            </p>

            <a href="{{ route('home') }}"
               class="shrink-0 rounded-pill bg-ink px-6 py-3 text-sm font-bold text-cream transition duration-300 ease-tavo hover:-translate-y-0.5">
                {{ $site->brand_name }}
            </a>
        </div>
    </footer>
</body>
</html>
