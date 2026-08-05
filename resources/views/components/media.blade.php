@props([
    'image' => null,          // pole z ResponsiveImage::make() — src, srcset, width, height, alt
    'label' => null,          // popisek placeholderu, dokud není nahraná fotka
    'ratio' => 'aspect-[4/3]',
    'radius' => 'rounded-media',
    'tone' => 'light',        // light | dark
    'parallax' => false,
    'fit' => 'cover',         // cover = ořízne na daný poměr | natural = nechá obrázku vlastní poměr
    'sizes' => '(min-width: 861px) 45vw, 88vw',
    'priority' => false,      // true jen u obrázku na první obrazovce
])

@php
    $gradient = $tone === 'dark'
        ? 'bg-gradient-to-br from-ink-lift to-ink'
        : 'bg-gradient-to-br from-sand-100 to-sand-400';
    $hatch = $tone === 'dark' ? 'hatch-dark' : 'hatch-light';
    $labelColor = $tone === 'dark' ? 'text-cream/40' : 'text-ink/40';

    /*
     * „natural" dává smysl jen když obrázek opravdu je — zástupný vizuál musí mít
     * pevný poměr, jinak by měl nulovou výšku. Poměr proto vypínáme až ve chvíli,
     * kdy výšku určí sám obrázek.
     */
    $natural = $fit === 'natural' && $image;
@endphp

<div {{ $attributes->class([$ratio => ! $natural, $radius, 'relative overflow-hidden', $gradient]) }}
     @if ($parallax) data-parallax-wrap @endif>
    @if ($image)
        {{--
            width/height drží prohlížeči místo, než se obrázek stáhne, takže
            stránka pod ním neposkočí. U ořezaných obrázků je přebije poměr
            stran z `aspect-*`, u „natural" určují výšku samy.

            Obrázek na první obrazovce se načítá přednostně (priority), zbytek
            až když se k němu doscrolluje.
        --}}
        <img src="{{ $image['src'] }}"
             @if ($image['srcset']) srcset="{{ $image['srcset'] }}" sizes="{{ $sizes }}" @endif
             alt="{{ $image['alt'] }}"
             @if ($image['width']) width="{{ $image['width'] }}" @endif
             @if ($image['height']) height="{{ $image['height'] }}" @endif
             loading="{{ $priority ? 'eager' : 'lazy' }}"
             @if ($priority) fetchpriority="high" @endif
             decoding="async"
             class="{{ $natural ? 'block h-auto w-full' : 'absolute inset-0 h-full w-full object-cover' }}">
    @else
        <div class="absolute inset-0 flex items-center justify-center {{ $hatch }}"
             @if ($parallax) data-parallax @endif>
            @if ($label)
                <span class="text-xs font-semibold tracking-[.14em] uppercase {{ $labelColor }}">{{ $label }}</span>
            @endif
        </div>
    @endif
</div>
