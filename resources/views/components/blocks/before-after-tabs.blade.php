@props(['data'])

{{-- Víc porovnání „před a po" přepínaných záložkami. Hotové obrazovky
     (s oběma obrázky) chystá HasContentBlocks do `screens_ready`; bez nich
     se sekce nevysází, takže prázdné záložky můžou čekat na doplnění. --}}
@if (! empty($data['screens_ready']))
    <section data-block-bg="{{ ($data['tone'] ?? 'cream') === 'ink' ? 'ink' : 'cream' }}"
             @class([
                 'section-x section-y-sm',
                 'bg-ink text-cream' => ($data['tone'] ?? 'cream') === 'ink',
                 'bg-cream text-ink' => ($data['tone'] ?? 'cream') !== 'ink',
             ])
             x-data="{ active: 0 }">
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
                <p data-reveal @class([
                    'mt-0 mb-8 max-w-[60ch] text-[15px] leading-[1.55]',
                    'text-cream/70' => ($data['tone'] ?? 'cream') === 'ink',
                    'text-muted' => ($data['tone'] ?? 'cream') !== 'ink',
                ])>
                    {{ $data['perex'] }}
                </p>
            @endif

            @if (count($data['screens_ready']) > 1)
                <div data-reveal role="tablist" aria-label="{{ $data['title'] ?? 'Před a po' }}"
                     class="mb-6 flex flex-wrap gap-2">
                    @foreach ($data['screens_ready'] as $index => $screen)
                        <button type="button" role="tab"
                                id="ba-tab-{{ $index }}"
                                x-on:click="active = {{ $index }}"
                                x-bind:aria-selected="active === {{ $index }}"
                                x-bind:class="active === {{ $index }}
                                    ? 'bg-brick text-white border-brick'
                                    : '{{ ($data['tone'] ?? 'cream') === 'ink' ? 'border-cream/25 text-cream/80 hover:border-cream/60' : 'border-ink/20 text-ink/80 hover:border-ink/50' }}'"
                                class="cursor-pointer rounded-pill border px-[18px] py-[9px] text-sm font-bold transition-colors duration-300">
                            {{ $screen['label'] }}
                        </button>
                    @endforeach
                </div>
            @endif

            @foreach ($data['screens_ready'] as $index => $screen)
                <div role="tabpanel" aria-labelledby="ba-tab-{{ $index }}"
                     @if ($index > 0) x-cloak @endif
                     x-show="active === {{ $index }}">
                    <x-before-after-slider
                        :before="$screen['before_image']"
                        :after="$screen['after_image']"
                        :before-label="'Před'"
                        :after-label="'Po'" />

                    @if (filled($screen['text'] ?? null))
                        <p @class([
                            'mt-5 mb-0 max-w-[70ch] text-[15px] leading-[1.55]',
                            'text-cream/70' => ($data['tone'] ?? 'cream') === 'ink',
                            'text-body' => ($data['tone'] ?? 'cream') !== 'ink',
                        ])>
                            {{ $screen['text'] }}
                        </p>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
@endif
