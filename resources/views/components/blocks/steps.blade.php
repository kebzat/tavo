@props(['data'])

@php
    $dark = ($data['tone'] ?? 'ink') === 'ink';
    $items = collect($data['items'] ?? [])->filter(fn ($item) => filled($item['title'] ?? null))->values();

    // Celé literály — skládaná třída („menu:grid-cols-“ . $n) by se do CSS nedostala.
    $columns = $items->count() >= 4 ? 'menu:grid-cols-4' : 'menu:grid-cols-3';
@endphp

@if ($items->isNotEmpty())
    <section data-block-bg="{{ $dark ? 'ink' : 'cream' }}"
             class="section-x section-y-sm {{ $dark ? 'bg-ink text-cream' : 'bg-cream text-ink' }}">
        <div class="container-tavo">
            @if (filled($data['eyebrow'] ?? null))
                <x-eyebrow data-reveal class="mb-5">{{ $data['eyebrow'] }}</x-eyebrow>
            @endif

            @if (filled($data['title'] ?? null))
                <h2 data-reveal class="text-h2 mt-0 mb-[46px] max-w-[18ch] font-extrabold tracking-[-.02em]">
                    {{ $data['title'] }}
                </h2>
            @endif

            <div class="grid grid-cols-2 gap-5 {{ $columns }}">
                @foreach ($items as $i => $step)
                    <div data-reveal class="border-t-2 {{ $dark ? 'border-cream/30' : 'border-ink/20' }} pt-[22px]">
                        <div class="mb-3.5 text-[13px] font-bold text-brick">
                            {{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}
                        </div>
                        <div class="text-step mb-2.5 font-extrabold tracking-[-.01em]">{{ $step['title'] }}</div>
                        @if (filled($step['text'] ?? null))
                            <div class="text-sm leading-[1.5] {{ $dark ? 'text-cream/65' : 'text-muted' }}">
                                {{ $step['text'] }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
