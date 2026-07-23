@props([
    'images' => [],   // [{url, alt, width, height}]
])

@php
    $images = collect($images)->values();
    $count = $images->count();
@endphp

@if ($count)
    {{--
        Rozvržení podle počtu obrázků:
        1 obrázek  → užší sloupec, ať nepůsobí jako bannerová hlavička
        2 a víc    → dva sloupce; CSS columns nechá každému obrázku vlastní výšku,
                     takže se nic neořezává ani při různých poměrech stran
    --}}
    <div x-data="tavoLightbox(@js($images))"
         @keydown.escape.window="close()"
         @keydown.arrow-left.window="prev()"
         @keydown.arrow-right.window="next()">

        <div class="{{ $count === 1 ? 'mx-auto max-w-[980px]' : 'gap-6 [column-fill:_balance] menu:columns-2' }}">
            @foreach ($images as $index => $image)
                <button type="button"
                        data-reveal
                        @click="open({{ $index }})"
                        class="group mb-6 block w-full cursor-zoom-in break-inside-avoid overflow-hidden rounded-card bg-gradient-to-br from-sand-100 to-sand-400 last:mb-0"
                        aria-label="Zvětšit obrázek: {{ $image['alt'] }}">
                    <img src="{{ $image['url'] }}"
                         alt="{{ $image['alt'] }}"
                         @if ($image['width'] && $image['height'])
                             width="{{ $image['width'] }}" height="{{ $image['height'] }}"
                         @endif
                         loading="lazy"
                         decoding="async"
                         class="block h-auto w-full transition-transform duration-500 ease-tavo group-hover:scale-[1.02]">
                </button>
            @endforeach
        </div>

        {{-- Lightbox --}}
        <div x-show="isOpen"
             x-cloak
             x-transition.opacity
             role="dialog"
             aria-modal="true"
             aria-label="Zvětšený obrázek"
             class="fixed inset-0 z-[70] flex items-center justify-center bg-ink/95 p-4 menu:p-10"
             @click.self="close()">

            <button type="button"
                    @click="close()"
                    x-ref="closeButton"
                    aria-label="Zavřít"
                    class="absolute top-4 right-4 flex h-12 w-12 items-center justify-center rounded-pill bg-cream/10 text-2xl text-cream transition hover:bg-cream hover:text-ink menu:top-6 menu:right-6">
                ×
            </button>

            <template x-if="images.length > 1">
                <button type="button"
                        @click="prev()"
                        aria-label="Předchozí obrázek"
                        class="absolute left-3 flex h-12 w-12 items-center justify-center rounded-pill bg-cream/10 text-2xl text-cream transition hover:bg-cream hover:text-ink menu:left-6">
                    ‹
                </button>
            </template>

            <template x-if="images.length > 1">
                <button type="button"
                        @click="next()"
                        aria-label="Další obrázek"
                        class="absolute right-3 flex h-12 w-12 items-center justify-center rounded-pill bg-cream/10 text-2xl text-cream transition hover:bg-cream hover:text-ink menu:right-6">
                    ›
                </button>
            </template>

            <figure class="m-0 flex max-h-full max-w-full flex-col items-center gap-4">
                <img :src="current.url"
                     :alt="current.alt"
                     class="max-h-[80vh] w-auto max-w-full rounded-card object-contain">
                <figcaption class="text-center text-[13px] text-cream/70">
                    <span x-text="current.alt"></span>
                    <template x-if="images.length > 1">
                        <span class="ml-2 text-cream/45" x-text="`${index + 1} / ${images.length}`"></span>
                    </template>
                </figcaption>
            </figure>
        </div>
    </div>
@endif
