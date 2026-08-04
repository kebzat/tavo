@props(['data'])

@php
    $dark = ($data['tone'] ?? 'ink') === 'ink';
    $before = $data['before_url'] ?? null;
    $after = $data['after_url'] ?? null;
    $beforeLabel = $data['before_label'] ?? null ?: 'Před';
    $afterLabel = $data['after_label'] ?? null ?: 'Po';
@endphp

{{-- Bez obou obrázků není co porovnávat, takže se sekce vůbec nevysází. --}}
@if ($before && $after)
    <section data-block-bg="{{ $dark ? 'ink' : 'cream' }}"
             class="section-x section-y-sm {{ $dark ? 'bg-ink text-cream' : 'bg-cream text-ink' }}">
        <div class="container-tavo">
            @if (filled($data['eyebrow'] ?? null))
                <x-eyebrow data-reveal class="mb-5">{{ $data['eyebrow'] }}</x-eyebrow>
            @endif

            @if (filled($data['title'] ?? null))
                <h2 data-reveal class="text-h2-sm mt-0 mb-4 max-w-[20ch] font-extrabold tracking-[-.02em]">
                    {{ $data['title'] }}
                </h2>
            @endif

            @if (filled($data['perex'] ?? null))
                <p data-reveal class="mt-0 mb-[42px] max-w-[60ch] text-[15px] leading-[1.55] {{ $dark ? 'text-cream/70' : 'text-muted' }}">
                    {{ $data['perex'] }}
                </p>
            @endif

            <div data-reveal
                 x-data="tavoBeforeAfter"
                 x-ref="frame"
                 @pointermove="track($event)"
                 @pointerdown="track($event)"
                 class="group relative aspect-[4/3] w-full cursor-ew-resize overflow-hidden rounded-media select-none menu:aspect-[16/8]">

                {{-- Spodní vrstva je stav „po", vrchní se ořezává podle polohy čáry. --}}
                <img src="{{ $after }}" alt="{{ $data['after_alt'] ?? '' }}"
                     draggable="false" loading="lazy" decoding="async"
                     class="absolute inset-0 h-full w-full object-cover object-top">

                <div class="absolute inset-0" x-bind:style="`clip-path: inset(0 ${100 - position}% 0 0)`">
                    <img src="{{ $before }}" alt="{{ $data['before_alt'] ?? '' }}"
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

            @if (filled($data['caption'] ?? null))
                <p data-reveal class="mt-5 mb-0 text-[13px] leading-[1.5] {{ $dark ? 'text-cream/50' : 'text-ink/60' }}">
                    {{ $data['caption'] }}
                </p>
            @endif
        </div>
    </section>
@endif
