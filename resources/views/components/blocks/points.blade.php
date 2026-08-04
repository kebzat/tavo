@props(['data'])

@php
    $dark = ($data['tone'] ?? 'ink') === 'ink';
    // values() drží číslování souvislé i po vyhození prázdných bodů.
    $items = collect($data['items'] ?? [])->filter(fn ($item) => filled($item))->values();
    $hasIntro = filled($data['eyebrow'] ?? null) || filled($data['title'] ?? null);
@endphp

@if ($items->isNotEmpty() || $hasIntro)
    <section data-block-bg="{{ $dark ? 'ink' : 'cream' }}"
             class="section-x section-y-sm {{ $dark ? 'bg-ink text-cream' : 'bg-cream text-ink' }}">
        <div class="container-tavo">
            <div class="grid grid-cols-1 items-start gap-[clamp(40px,6vw,110px)] menu:grid-cols-2">
                <div>
                    @if (filled($data['eyebrow'] ?? null))
                        <x-eyebrow data-reveal class="mb-[22px]">{{ $data['eyebrow'] }}</x-eyebrow>
                    @endif

                    @if (filled($data['title'] ?? null))
                        <h2 data-reveal class="text-h2-lg m-0 font-extrabold tracking-[-.02em]">{{ $data['title'] }}</h2>
                    @endif
                </div>

                <div class="pt-2.5">
                    @if (filled($data['perex'] ?? null))
                        <p data-reveal class="text-body-lg mt-0 mb-[30px] {{ $dark ? 'text-cream/78' : 'text-body' }}">
                            {{ $data['perex'] }}
                        </p>
                    @endif

                    @if ($items->isNotEmpty())
                        <div data-reveal class="flex flex-col">
                            @foreach ($items as $i => $point)
                                <div class="flex items-baseline gap-[18px] border-t {{ $dark ? 'border-cream/15' : 'border-ink/12' }} py-4">
                                    <span class="text-sm font-bold text-brick">
                                        {{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}
                                    </span>
                                    <span class="text-base leading-[1.4]">{{ $point }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endif
