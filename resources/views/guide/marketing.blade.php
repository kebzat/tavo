{{--
    Průvodce pro Pavla po nástrojích marketingu. Obsah skládá
    App\Support\Marketing\MarketingGuide, šablona jen vykresluje.
--}}
<x-layout.document :title="$guide['title']" :eyebrow="$guide['eyebrow']" note="Pracovní dokument. Když nástroje změníme, upravíme i průvodce.">

    <section class="section-x pt-6 pb-2">
        <div class="container-tavo">
            <div class="rounded-card bg-ink px-[7vw] py-11 text-cream menu:px-14 menu:py-14">
                <div class="flex flex-col gap-10 menu:flex-row menu:items-end menu:justify-between">
                    <div class="max-w-[52ch]">
                        <p class="mb-4 text-sm font-bold tracking-[.14em] text-brick uppercase">Pro Pavla</p>

                        <h1 data-line class="text-h2-sm font-extrabold tracking-[-.02em]">{{ $guide['title'] }}</h1>

                        <p class="mt-5 text-perex text-cream/70">{{ $guide['intro'] }}</p>
                    </div>

                    <dl class="flex shrink-0 gap-8 menu:gap-10">
                        @foreach ($guide['counts'] as $count)
                            <div>
                                <dt class="sr-only">{{ $count['label'] }}</dt>
                                <dd class="text-metric font-extrabold tracking-[-.03em] {{ $loop->first ? 'text-brick' : 'text-cream' }}">{{ $count['value'] }}</dd>
                                <dd class="mt-1 text-sm font-semibold text-cream/60">{{ $count['label'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </div>
        </div>
    </section>

    <section class="section-x pt-10 menu:pt-14" aria-labelledby="kde-zacit">
        <div class="container-tavo">
            <h2 id="kde-zacit" class="text-h3-sm font-extrabold tracking-[-.02em] text-ink">Kde začít</h2>

            <ol class="mt-6 grid gap-4 menu:grid-cols-3">
                @foreach ($guide['start'] as $step)
                    <li class="rounded-card border border-ink/14 p-6">
                        <p class="text-sm font-bold tracking-[.14em] text-brick uppercase tabular-nums">{{ sprintf('%02d', $loop->iteration) }} · {{ $step['title'] }}</p>
                        <p class="mt-3 text-[15px] leading-[1.55] text-body">{{ $step['text'] }}</p>
                    </li>
                @endforeach
            </ol>

            <nav aria-label="Obsah průvodce" class="mt-8 flex flex-wrap gap-2">
                @foreach ($guide['features'] as $feature)
                    <a href="#{{ $feature['id'] }}"
                       class="rounded-pill border-[1.5px] border-ink/20 px-4 py-2 text-sm font-semibold text-ink transition duration-300 ease-tavo hover:border-ink hover:bg-ink hover:text-cream">
                        {{ $feature['title'] }}
                    </a>
                @endforeach
            </nav>
        </div>
    </section>

    <div class="section-x pt-14 pb-6 menu:pt-20">
        <div class="container-tavo flex flex-col gap-20 menu:gap-28">
            @foreach ($guide['features'] as $feature)
                <section id="{{ $feature['id'] }}" class="scroll-mt-6" aria-labelledby="{{ $feature['id'] }}-nadpis">
                    <div class="grid gap-8 menu:grid-cols-[minmax(0,1fr)_minmax(0,1.35fr)] menu:gap-14">
                        <div>
                            <p class="text-sm font-bold tracking-[.14em] text-muted uppercase tabular-nums">{{ sprintf('%02d', $loop->iteration) }}</p>
                            <h2 id="{{ $feature['id'] }}-nadpis" class="mt-2 text-h3 font-extrabold tracking-[-.02em] text-ink">{{ $feature['title'] }}</h2>

                            <figure class="mt-6 border-l-[3px] border-brick pl-5">
                                <blockquote class="text-[15px] leading-[1.55] text-body italic">„{{ $feature['asked'] }}“</blockquote>
                                <figcaption class="mt-2 text-sm font-semibold text-muted">Tvoje odpověď, otázka {{ $feature['question'] }}</figcaption>
                            </figure>
                        </div>

                        <div>
                            <p class="text-sm font-bold tracking-[.14em] text-muted uppercase">Kde to najdeš</p>
                            <ol class="mt-3 flex flex-wrap items-center gap-x-1.5 gap-y-2" aria-label="Cesta v nástrojích">
                                @foreach ($feature['where'] as $place)
                                    <li class="flex items-center gap-1.5">
                                        <span class="rounded-pill bg-ink px-3.5 py-1.5 text-sm font-semibold text-cream">{{ $place }}</span>
                                        @unless ($loop->last)
                                            <span class="text-muted" aria-hidden="true">›</span>
                                        @endunless
                                    </li>
                                @endforeach
                            </ol>

                            <p class="mt-7 text-sm font-bold tracking-[.14em] text-muted uppercase">Jak s tím pracovat</p>
                            <ul class="mt-3 flex flex-col gap-3">
                                @foreach ($feature['how'] as $line)
                                    <li class="flex gap-3 text-[15px] leading-[1.6] text-body">
                                        <span class="mt-[9px] size-1.5 shrink-0 rounded-full bg-brick" aria-hidden="true"></span>
                                        <span>{{ $line }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>

                    <a href="{{ $feature['src'] }}" target="_blank" rel="noopener"
                       style="max-width: {{ $feature['width'] }}px"
                       class="mt-8 block overflow-hidden rounded-media border border-ink/14 bg-white shadow-[0_24px_50px_-30px_rgb(19_17_16/.45)] transition duration-300 ease-tavo hover:-translate-y-0.5 menu:mt-10">
                        <img src="{{ $feature['src'] }}" alt="{{ $feature['alt'] }}"
                             width="{{ $feature['width'] }}" height="{{ $feature['height'] }}"
                             loading="lazy" decoding="async" class="block h-auto w-full">
                    </a>
                </section>
            @endforeach
        </div>
    </div>

    <section class="section-x pt-16 menu:pt-24" aria-labelledby="napul">
        <div class="container-tavo grid gap-12 menu:grid-cols-2 menu:gap-14">
            <div>
                <h2 id="napul" class="text-h3-sm font-extrabold tracking-[-.02em] text-ink">Hotové napůl</h2>
                <ul class="mt-6 flex flex-col gap-3">
                    @foreach ($guide['partial'] as $item)
                        <li class="rounded-card border border-ink/14 p-5">
                            <p class="font-bold text-ink">{{ $item['title'] }}</p>
                            <p class="mt-1.5 text-[15px] leading-[1.55] text-body">{{ $item['text'] }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div>
                <h2 class="text-h3-sm font-extrabold tracking-[-.02em] text-ink">Zatím chybí</h2>
                <ul class="mt-6 divide-y divide-ink/10 border-y border-ink/10">
                    @foreach ($guide['missing'] as $item)
                        <li class="grid gap-1 py-3.5 sm:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)] sm:gap-4">
                            <p class="font-bold text-ink">{{ $item['title'] }}</p>
                            <p class="text-[15px] leading-[1.55] text-muted">{{ $item['text'] }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    <section class="section-x pt-16 pb-16 menu:pt-24 menu:pb-24">
        <div class="container-tavo grid gap-6 menu:grid-cols-2">
            <div class="rounded-card bg-ink p-7 text-cream menu:p-10">
                <h2 class="text-h3-sm font-extrabold tracking-[-.02em]">Co je hlídané</h2>
                <ul class="mt-6 flex flex-col gap-4">
                    @foreach ($guide['safety'] as $line)
                        <li class="flex gap-3 text-[15px] leading-[1.6] text-cream/80">
                            <span class="mt-[9px] size-1.5 shrink-0 rounded-full bg-brick" aria-hidden="true"></span>
                            <span>{{ $line }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="rounded-card border border-ink/14 p-7 menu:p-10">
                <h2 class="text-h3-sm font-extrabold tracking-[-.02em] text-ink">Co potřebuju od tebe</h2>
                <ol class="mt-6 flex flex-col gap-4">
                    @foreach ($guide['asks'] as $line)
                        <li class="flex gap-3 text-[15px] leading-[1.6] text-body">
                            <span class="shrink-0 font-bold text-brick tabular-nums">{{ $loop->iteration }}.</span>
                            <span>{{ $line }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
    </section>
</x-layout.document>
