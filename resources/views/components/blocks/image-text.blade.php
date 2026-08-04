@props(['data'])

@php
    $dark = ($data['tone'] ?? 'cream') === 'ink';
    $imageLeft = ($data['side'] ?? 'left') === 'left';
    $image = $data['image_url'] ?? null;
    $label = $data['image_label'] ?? null;

    // Dokud není nahraná fotka, drží místo šrafovaný zástupný vizuál s popiskem.
    // Bez popisku se sekce vysází jen jako text, aby na stránce nezel prázdný obdélník.
    $hasVisual = filled($image) || filled($label);
    $hasText = filled($data['eyebrow'] ?? null) || filled($data['title'] ?? null) || filled($data['body'] ?? null);
@endphp

@if ($hasVisual || $hasText)
    <section data-block-bg="{{ $dark ? 'ink' : 'cream' }}"
             class="section-x section-y-sm {{ $dark ? 'bg-ink text-cream' : 'bg-cream text-ink' }}">
        <div class="container-tavo grid grid-cols-1 items-center gap-[clamp(30px,5vw,80px)] {{ $hasVisual && $hasText ? 'menu:grid-cols-2' : 'max-w-[900px]' }}">
            @if ($hasVisual)
                <x-media data-reveal
                         :url="$image"
                         :alt="$data['image_alt'] ?? ''"
                         :label="$label"
                         :tone="$dark ? 'dark' : 'light'"
                         class="{{ $imageLeft ? 'menu:order-1' : 'menu:order-2' }}" />
            @endif

            @if ($hasText)
                <div data-reveal class="{{ $imageLeft ? 'menu:order-2' : 'menu:order-1' }}">
                    @if (filled($data['eyebrow'] ?? null))
                        <x-eyebrow class="mb-5">{{ $data['eyebrow'] }}</x-eyebrow>
                    @endif

                    @if (filled($data['title'] ?? null))
                        <h2 class="text-h2-sm m-0 font-extrabold tracking-[-.02em]">{{ $data['title'] }}</h2>
                    @endif

                    @if (filled($data['body'] ?? null))
                        <div class="prose-tavo mt-6 {{ $dark ? 'prose-tavo-dark' : '' }}">
                            {!! $data['body'] !!}
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </section>
@endif
