@props(['data'])

{{-- Karty s náhledem: co prvek dělá a proč se vyplatí. Karta bez nadpisu
     se přeskočí, bez jediné karty se sekce nevysází. --}}
@if (collect($data['items'] ?? [])->pluck('title')->filter()->isNotEmpty())
    <section data-block-bg="{{ ($data['tone'] ?? 'cream') === 'ink' ? 'ink' : 'cream' }}"
             @class([
                 'section-x section-y-sm',
                 'bg-ink text-cream' => ($data['tone'] ?? 'cream') === 'ink',
                 'bg-cream text-ink' => ($data['tone'] ?? 'cream') !== 'ink',
             ])>
        <div class="container-tavo">
            @if (filled($data['eyebrow'] ?? null))
                <x-eyebrow data-reveal class="mb-5">{{ $data['eyebrow'] }}</x-eyebrow>
            @endif

            @if (filled($data['title'] ?? null))
                <h2 data-reveal class="text-h2 mt-0 mb-5 max-w-[20ch] font-extrabold tracking-[-.02em]">
                    {{ $data['title'] }}
                </h2>
            @endif

            @if (filled($data['perex'] ?? null))
                <p data-reveal @class([
                    'mt-0 mb-[46px] max-w-[62ch] text-perex',
                    'text-cream/70' => ($data['tone'] ?? 'cream') === 'ink',
                    'text-muted' => ($data['tone'] ?? 'cream') !== 'ink',
                ])>
                    {{ $data['perex'] }}
                </p>
            @endif

            {{-- Celé literály, skládaná třída by se do CSS nedostala. --}}
            <div @class([
                'grid grid-cols-1 gap-6',
                'menu:grid-cols-2' => (int) ($data['columns'] ?? 3) === 2,
                'menu:grid-cols-2 loop:grid-cols-3' => (int) ($data['columns'] ?? 3) !== 2,
            ])>
                @foreach ($data['items'] ?? [] as $item)
                    @continue(blank($item['title'] ?? null))

                    <article data-reveal @class([
                        'flex flex-col overflow-hidden rounded-card border',
                        'border-cream/15 bg-ink-soft' => ($data['tone'] ?? 'cream') === 'ink',
                        'border-ink/12 bg-cream' => ($data['tone'] ?? 'cream') !== 'ink',
                    ])>
                        @if ($item['image_image'] ?? null)
                            <x-media :image="$item['image_image']"
                                     radius="rounded-none"
                                     :tone="($data['tone'] ?? 'cream') === 'ink' ? 'dark' : 'light'"
                                     sizes="(min-width: 1101px) 28vw, (min-width: 861px) 44vw, 88vw" />
                        @endif

                        <div class="flex flex-1 flex-col p-7">
                            @if (filled($item['tag'] ?? null))
                                <x-tag :tone="($data['tone'] ?? 'cream') === 'ink' ? 'ghost' : 'light'" size="xs" class="mb-4 self-start">
                                    {{ $item['tag'] }}
                                </x-tag>
                            @endif

                            <h3 class="mt-0 mb-4 text-xl leading-[1.2] font-extrabold tracking-[-.01em]">{{ $item['title'] }}</h3>

                            @if (filled($item['what'] ?? null))
                                <p class="mt-0 mb-1 text-[11px] font-bold tracking-[.1em] text-brick uppercase">
                                    {{ text('bloky.karty_co_dela', 'Co to dělá', 'Bloky', 'Popisek v kartách s ukázkou') }}
                                </p>
                                <p @class([
                                    'mt-0 mb-4 text-[15px] leading-[1.55]',
                                    'text-cream/75' => ($data['tone'] ?? 'cream') === 'ink',
                                    'text-body' => ($data['tone'] ?? 'cream') !== 'ink',
                                ])>{{ $item['what'] }}</p>
                            @endif

                            @if (filled($item['why'] ?? null))
                                <p class="mt-0 mb-1 text-[11px] font-bold tracking-[.1em] text-brick uppercase">
                                    {{ text('bloky.karty_proc', 'Proč se to vyplatí', 'Bloky', 'Popisek v kartách s ukázkou') }}
                                </p>
                                <p @class([
                                    'mt-0 mb-4 text-[15px] leading-[1.55]',
                                    'text-cream/60' => ($data['tone'] ?? 'cream') === 'ink',
                                    'text-muted' => ($data['tone'] ?? 'cream') !== 'ink',
                                ])>{{ $item['why'] }}</p>
                            @endif

                            @if (filled($item['link_url'] ?? null))
                                <a href="{{ $item['link_url'] }}"
                                   class="mt-auto inline-flex items-center gap-2 self-start pt-2 text-sm font-bold text-brick no-underline hover:underline">
                                    {{ $item['link_label'] ?? null ?: text('bloky.karty_odkaz', 'Ukázka v referenci', 'Bloky', 'Výchozí text odkazu v kartách s ukázkou') }}
                                    <span aria-hidden="true">→</span>
                                </a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endif
