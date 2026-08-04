@props(['data'])

@php
    $dark = ($data['tone'] ?? 'ink') === 'ink';
    $items = collect($data['items'] ?? [])->filter(fn ($item) => filled($item['value'] ?? null));

    // Celé literály — skládaná třída („menu:grid-cols-“ . $n) by se do CSS nedostala.
    $columns = $items->count() >= 3 ? 'menu:grid-cols-3' : 'menu:grid-cols-2';
@endphp

@if ($items->isNotEmpty())
    <section data-block-bg="{{ $dark ? 'ink' : 'cream' }}"
             class="section-x section-y-sm {{ $dark ? 'bg-ink text-cream' : 'bg-cream text-ink' }}">
        <div class="container-tavo">
            @if (filled($data['title'] ?? null))
                <h2 data-reveal class="text-h2 mt-0 mb-[46px] max-w-[20ch] font-extrabold tracking-[-.02em]">
                    {{ $data['title'] }}
                </h2>
            @endif

            <div data-reveal class="grid grid-cols-1 gap-x-[30px] gap-y-10 {{ $columns }}">
                @foreach ($items as $item)
                    <div class="border-t-2 {{ $dark ? 'border-cream/25' : 'border-ink/15' }} pt-6">
                        <div class="text-metric-lg font-extrabold tracking-[-.03em] text-brick">{{ $item['value'] }}</div>
                        @if (filled($item['label'] ?? null))
                            <div class="mt-3.5 max-w-[26ch] text-[15px] leading-[1.5] {{ $dark ? 'text-cream/70' : 'text-muted' }}">
                                {{ $item['label'] }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            @if (filled($data['note'] ?? null))
                <p data-reveal class="mt-12 mb-0 max-w-[80ch] text-[13px] leading-[1.6] {{ $dark ? 'text-cream/50' : 'text-ink/60' }}">
                    {{ $data['note'] }}
                </p>
            @endif
        </div>
    </section>
@endif
