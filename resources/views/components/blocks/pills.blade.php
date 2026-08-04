@props(['data'])

@php
    $dark = ($data['tone'] ?? 'cream') === 'ink';
    $items = collect($data['items'] ?? [])->filter(fn ($item) => filled($item))->values();
@endphp

@if ($items->isNotEmpty())
    <section data-block-bg="{{ $dark ? 'ink' : 'cream' }}"
             class="section-x section-y-sm {{ $dark ? 'bg-ink text-cream' : 'bg-cream text-ink' }}">
        <div class="container-tavo grid grid-cols-1 items-start gap-[clamp(30px,5vw,80px)] menu:grid-cols-[0.9fr_1.1fr]">
            <div>
                @if (filled($data['eyebrow'] ?? null))
                    <x-eyebrow data-reveal class="mb-5">{{ $data['eyebrow'] }}</x-eyebrow>
                @endif

                @if (filled($data['title'] ?? null))
                    <h2 data-reveal class="text-h2-sm m-0 font-extrabold tracking-[-.02em]">{{ $data['title'] }}</h2>
                @endif

                @if (filled($data['perex'] ?? null))
                    <p data-reveal class="mt-5 mb-0 max-w-[46ch] text-[15px] leading-[1.55] {{ $dark ? 'text-cream/70' : 'text-muted' }}">
                        {{ $data['perex'] }}
                    </p>
                @endif
            </div>

            <div data-reveal class="flex flex-wrap gap-2.5 menu:pt-2">
                @foreach ($items as $item)
                    <x-tag :tone="$dark ? 'ghost' : 'light'" size="sm">{{ $item }}</x-tag>
                @endforeach
            </div>
        </div>
    </section>
@endif
