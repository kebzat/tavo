@props([
    'title' => null,
    'description' => null,
    'ogImage' => null,
    'schema' => [],
])

@php
    $seo = app(App\Settings\SeoSettings::class);
    $pageTitle = $title ? $title.$seo->title_suffix : $seo->default_title.$seo->title_suffix;
    $pageDescription = $description ?: $seo->default_description;
    $image = $ogImage ?: $seo->og_image;
    $imageUrl = $image ? (str_starts_with($image, 'http') ? $image : url($image)) : null;
    $documents = array_merge([App\Support\StructuredData::professionalService()], $schema);
@endphp

<title>{{ $pageTitle }}</title>
<meta name="description" content="{{ $pageDescription }}">
<link rel="canonical" href="{{ url()->current() }}">

@unless ($seo->indexable)
    <meta name="robots" content="noindex, nofollow">
@endunless

<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:locale" content="cs_CZ">
<meta property="og:title" content="{{ $pageTitle }}">
<meta property="og:description" content="{{ $pageDescription }}">
<meta property="og:url" content="{{ url()->current() }}">
@if ($imageUrl)
    <meta property="og:image" content="{{ $imageUrl }}">
@endif

<meta name="twitter:card" content="{{ $imageUrl ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $pageTitle }}">
<meta name="twitter:description" content="{{ $pageDescription }}">

@foreach ($documents as $document)
    <script type="application/ld+json">
        {!! json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
@endforeach

@if ($seo->gtm_id)
    <script>
        // GTM se načte až po souhlasu — viz components/cookie-bar.blade.php
        window.__tavoGtmId = @json($seo->gtm_id);
    </script>
@endif
