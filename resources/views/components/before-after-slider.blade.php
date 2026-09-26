@props([
    'before',                 // pole z ResponsiveImage::make()
    'after',
    'beforeLabel' => 'Před',
    'afterLabel' => 'Po',
    'sizes' => '(min-width: 861px) 88vw, 88vw',
])

{{-- Posuvník „před a po": dva snímky přes sebe, čára sleduje myš, bez myši
     se posouvá šipkami. Používá ho blok „Před a po" i homepage. --}}
@php
    // Rám přebírá poměr snímku „po", takže je vidět celý i na mobilu.
    // Snímek „před" se do něj ořízne odshora, proto oba nahrávat ve stejném rozměru.
    $ratio = ($after['width'] ?? null) && ($after['height'] ?? null)
        ? $after['width'].' / '.$after['height']
        : '16 / 8';
@endphp

<div {{ $attributes->class(['group relative w-full cursor-ew-resize overflow-hidden rounded-media select-none']) }}
     x-data="tavoBeforeAfter"
     x-ref="frame"
     @pointermove="track($event)"
     @pointerdown="track($event)"
     style="aspect-ratio: {{ $ratio }}">

    {{-- Spodní vrstva je stav „po", vrchní se ořezává podle polohy čáry. --}}
    <img src="{{ $after['src'] }}"
         @if ($after['srcset']) srcset="{{ $after['srcset'] }}" sizes="{{ $sizes }}" @endif
         alt="{{ $after['alt'] }}"
         @if ($after['width']) width="{{ $after['width'] }}" height="{{ $after['height'] }}" @endif
         draggable="false" loading="lazy" decoding="async"
         class="absolute inset-0 h-full w-full object-cover object-top">

    <div class="absolute inset-0" x-bind:style="`clip-path: inset(0 ${100 - position}% 0 0)`">
        <img src="{{ $before['src'] }}"
             @if ($before['srcset']) srcset="{{ $before['srcset'] }}" sizes="{{ $sizes }}" @endif
             alt="{{ $before['alt'] }}"
             @if ($before['width']) width="{{ $before['width'] }}" height="{{ $before['height'] }}" @endif
             draggable="false" loading="lazy" decoding="async"
             class="absolute inset-0 h-full w-full object-cover object-top">
    </div>

    <div class="pointer-events-none absolute inset-y-0 z-10 w-0.5 -translate-x-1/2 bg-cream shadow-[0_0_20px_rgba(0,0,0,.45)]"
         x-bind:style="`left: ${position}%`">
        <span class="absolute top-1/2 left-1/2 flex h-11 w-11 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-pill bg-cream text-lg font-bold text-ink shadow-[0_6px_20px_rgba(0,0,0,.35)]">
            ↔
        </span>
    </div>

    <div class="pointer-events-none absolute top-4 left-4 z-10">
        <x-tag tone="dark" size="xs">{{ $beforeLabel }}</x-tag>
    </div>
    <div class="pointer-events-none absolute top-4 right-4 z-10">
        <x-tag tone="brick" size="xs">{{ $afterLabel }}</x-tag>
    </div>

    {{-- Bez myši se čára posouvá šipkami. Posuvník je skrytý, ale zaostřitelný. --}}
    <input type="range" min="0" max="100" step="1"
           x-model.number="position"
           class="sr-only"
           aria-label="Porovnání stavu {{ $beforeLabel }} a {{ $afterLabel }}">
</div>
