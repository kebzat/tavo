@props(['data'])

@php
    $dark = ($data['tone'] ?? 'ink') === 'ink';
    $before = $data['before_image'] ?? null;
    $after = $data['after_image'] ?? null;
    $sizes = '(min-width: 861px) 88vw, 88vw';
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

            <x-before-after-slider data-reveal
                :before="$before"
                :after="$after"
                :before-label="$beforeLabel"
                :after-label="$afterLabel"
                :sizes="$sizes" />

            @if (filled($data['caption'] ?? null))
                <p data-reveal class="mt-5 mb-0 text-[13px] leading-[1.5] {{ $dark ? 'text-cream/50' : 'text-ink/60' }}">
                    {{ $data['caption'] }}
                </p>
            @endif
        </div>
    </section>
@endif
