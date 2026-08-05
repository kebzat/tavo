@props(['data'])

@php
    $dark = ($data['tone'] ?? 'ink') === 'ink';

    $columns = collect($data['columns'] ?? [])
        ->map(fn ($column) => [
            'label' => $column['label'] ?? null,
            'items' => collect($column['items'] ?? [])->filter(fn ($item) => filled($item))->values(),
        ])
        ->filter(fn ($column) => $column['items']->isNotEmpty())
        ->values();

    // Jediný sloupec zabírá celou šířku, dva se rozdělí. Celé literály, ne fragmenty.
    $grid = $columns->count() > 1 ? 'menu:grid-cols-2' : '';
@endphp

@if ($columns->isNotEmpty())
    <section data-block-bg="{{ $dark ? 'ink' : 'cream' }}"
             class="section-x section-y-sm {{ $dark ? 'bg-ink text-cream' : 'bg-cream text-ink' }}">
        <div class="container-tavo">
            @if (filled($data['title'] ?? null))
                <h2 data-reveal class="text-h2 mt-0 mb-3 font-extrabold tracking-[-.02em]">{{ $data['title'] }}</h2>
            @endif

            @if (filled($data['perex'] ?? null))
                <p data-reveal class="text-perex mt-0 mb-[clamp(28px,3.4vw,44px)] max-w-[48ch] {{ $dark ? 'text-cream/65' : 'text-muted' }}">
                    {{ $data['perex'] }}
                </p>
            @endif

            <div data-reveal
                 class="grid grid-cols-1 gap-x-[clamp(40px,5vw,80px)] gap-y-[clamp(30px,4vw,50px)] border-t {{ $dark ? 'border-cream/18' : 'border-ink/14' }} pt-[clamp(28px,3.4vw,44px)] {{ $grid }}">
                @foreach ($columns as $column)
                    <div>
                        @if (filled($column['label']))
                            <div class="mb-4 text-[13px] font-bold tracking-[.12em] text-brick uppercase">
                                {{ $column['label'] }}
                            </div>
                        @endif

                        <ul class="m-0 flex list-none flex-col gap-3 p-0">
                            @foreach ($column['items'] as $item)
                                <li class="flex gap-3 text-base leading-[1.5] {{ $dark ? 'text-cream/82' : 'text-body' }}">
                                    <span class="font-bold text-brick">—</span>{{ $item }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>

            @if (filled($data['note'] ?? null))
                <p data-reveal class="mt-[clamp(28px,3.4vw,44px)] mb-0 max-w-[80ch] text-[13px] leading-[1.6] {{ $dark ? 'text-cream/50' : 'text-ink/60' }}">
                    {{ $data['note'] }}
                </p>
            @endif
        </div>
    </section>
@endif
