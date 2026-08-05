@props([
    'title' => null,
    'description' => null,
    'ogImage' => null,
    'ogImageAlt' => null,
    'schema' => [],
])

@php($meta = App\Support\PageMeta::build($title, $description, $ogImage, $ogImageAlt, $schema))

<title>{{ $meta['title'] }}</title>
@if ($meta['description'])
    <meta name="description" content="{{ $meta['description'] }}">
@endif
<link rel="canonical" href="{{ $meta['canonical'] }}">
<meta name="robots" content="{{ $meta['robots'] }}">

{{-- Barva lišty prohlížeče na mobilu — ať web nekončí u systémově šedého pruhu. --}}
<meta name="theme-color" content="#f4ede1">

<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:locale" content="cs_CZ">
<meta property="og:title" content="{{ $meta['title'] }}">
@if ($meta['description'])
    <meta property="og:description" content="{{ $meta['description'] }}">
@endif
<meta property="og:url" content="{{ $meta['canonical'] }}">
@if ($meta['image'])
    <meta property="og:image" content="{{ $meta['image'] }}">
    <meta property="og:image:alt" content="{{ $meta['imageAlt'] }}">
    @if ($meta['imageWidth'])
        <meta property="og:image:width" content="{{ $meta['imageWidth'] }}">
        <meta property="og:image:height" content="{{ $meta['imageHeight'] }}">
    @endif
@endif

<meta name="twitter:card" content="{{ $meta['image'] ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $meta['title'] }}">
@if ($meta['description'])
    <meta name="twitter:description" content="{{ $meta['description'] }}">
@endif
@if ($meta['image'])
    <meta name="twitter:image" content="{{ $meta['image'] }}">
    <meta name="twitter:image:alt" content="{{ $meta['imageAlt'] }}">
@endif

@foreach ($meta['schema'] as $document)
    <script type="application/ld+json">
        {!! json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
@endforeach

@if ($meta['gtmId'])
    <script>
        // GTM se načte až po souhlasu — viz components/cookie-bar.blade.php
        window.__tavoGtmId = @json($meta['gtmId']);
    </script>
@endif
