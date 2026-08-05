@props(['data'])

@php
    $tone = $data['tone'] ?? 'ink';
    $items = collect($data['items'] ?? [])->filter(fn ($item) => filled($item['value'] ?? null));

    // Celé literály — skládaná třída („menu:grid-cols-“ . $n) by se do CSS nedostala.
    $columns = $items->count() >= 3 ? 'menu:grid-cols-3' : 'menu:grid-cols-2';

    /*
     * Na cihlové je číslo tmavé, jinak by cihlová svítila na cihlové. Proto se
     * barvy nesou v poli a ne jako dvojice „tmavá / ostatní".
     */
    $styles = [
        'ink' => ['section' => 'bg-ink text-cream', 'rule' => 'border-cream/25', 'value' => 'text-brick', 'label' => 'text-cream/70', 'note' => 'text-cream/50'],
        'cream' => ['section' => 'bg-cream text-ink', 'rule' => 'border-ink/15', 'value' => 'text-brick', 'label' => 'text-muted', 'note' => 'text-ink/60'],
        'brick' => ['section' => 'bg-brick text-ink', 'rule' => 'border-ink', 'value' => 'text-ink', 'label' => 'text-ink/80', 'note' => 'text-ink/60'],
    ];

    $style = $styles[$tone] ?? $styles['ink'];
@endphp

@if ($items->isNotEmpty())
    <section data-block-bg="{{ $tone }}" class="section-x section-y-sm {{ $style['section'] }}">
        <div class="container-tavo">
            @if (filled($data['title'] ?? null))
                <h2 data-reveal class="text-h2 mt-0 mb-[46px] max-w-[20ch] font-extrabold tracking-[-.02em]">
                    {{ $data['title'] }}
                </h2>
            @endif

            <div data-reveal class="grid grid-cols-1 gap-x-[30px] gap-y-10 {{ $columns }}">
                @foreach ($items as $item)
                    <div class="border-t-2 {{ $style['rule'] }} pt-6">
                        <div class="text-metric-lg font-extrabold tracking-[-.03em] {{ $style['value'] }}">
                            {{ $item['value'] }}
                        </div>
                        @if (filled($item['label'] ?? null))
                            <div class="mt-3.5 max-w-[26ch] text-[15px] leading-[1.5] {{ $style['label'] }}">
                                {{ $item['label'] }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            @if (filled($data['note'] ?? null))
                <p data-reveal class="mt-12 mb-0 max-w-[80ch] text-[13px] leading-[1.6] {{ $style['note'] }}">
                    {{ $data['note'] }}
                </p>
            @endif
        </div>
    </section>
@endif
