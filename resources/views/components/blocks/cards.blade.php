@props(['data'])

@php
    $items = collect($data['items'] ?? [])->filter(fn ($item) => filled($item['title'] ?? null));

    // Celé literály — skládaná třída („menu:grid-cols-“ . $n) by se do CSS nedostala.
    $columns = (int) ($data['columns'] ?? 3) === 2
        ? 'menu:grid-cols-2'
        : 'menu:grid-cols-2 loop:grid-cols-3';
@endphp

@if ($items->isNotEmpty())
    <section data-block-bg="cream" class="section-x section-y-sm bg-cream">
        <div class="container-tavo">
            @if (filled($data['title'] ?? null))
                <h2 data-reveal class="text-h2 mt-0 mb-[46px] max-w-[18ch] font-extrabold tracking-[-.02em]">
                    {{ $data['title'] }}
                </h2>
            @endif

            <div class="grid grid-cols-1 gap-6 {{ $columns }}">
                @foreach ($items as $item)
                    <div data-reveal
                         class="rounded-card border border-ink/12 bg-cream p-8 transition-transform duration-500 ease-tavo hover:-translate-y-1.5">
                        <div class="mb-3 text-xl font-extrabold tracking-[-.01em]">{{ $item['title'] }}</div>
                        @if (filled($item['text'] ?? null))
                            <p class="m-0 text-[15px] leading-[1.55] text-muted">{{ $item['text'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
