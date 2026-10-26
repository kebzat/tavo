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

    <section id="{{ $guide['agent']['id'] }}" class="section-x scroll-mt-6 pt-10 menu:pt-14" aria-labelledby="agent-nadpis">
        <div class="container-tavo">
            <div class="rounded-card border-2 border-brick/70 p-6 menu:p-12">
                <div class="grid gap-8 menu:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)] menu:gap-14">
                    <div>
                        <p class="text-sm font-bold tracking-[.14em] text-brick uppercase">Nejdůležitější novinka</p>
                        <h2 id="agent-nadpis" class="mt-2 text-h3 font-extrabold tracking-[-.02em] text-ink">{{ $guide['agent']['title'] }}</h2>
                        <p class="mt-5 text-perex text-body">{{ $guide['agent']['intro'] }}</p>

                        <figure class="mt-6 border-l-[3px] border-brick pl-5">
                            <blockquote class="text-[15px] leading-[1.55] text-body italic">„{{ $guide['agent']['asked'] }}“</blockquote>
                            <figcaption class="mt-2 text-sm font-semibold text-muted">Tvoje odpověď, otázka {{ $guide['agent']['question'] }}</figcaption>
                        </figure>
                    </div>

                    <div>
                        <p class="text-sm font-bold tracking-[.14em] text-muted uppercase">Kde ho najdeš</p>
                        <ol class="mt-3 flex flex-wrap items-center gap-x-1.5 gap-y-2" aria-label="Cesta v nástrojích">
                            @foreach ($guide['agent']['where'] as $place)
                                <li class="flex items-center gap-1.5">
                                    <span class="rounded-pill bg-ink px-3.5 py-1.5 text-sm font-semibold text-cream">{{ $place }}</span>
                                    @unless ($loop->last)
                                        <span class="text-muted" aria-hidden="true">›</span>
                                    @endunless
                                </li>
                            @endforeach
                        </ol>

                        <p class="mt-7 text-sm font-bold tracking-[.14em] text-muted uppercase">Co udělá sám</p>
                        <ul class="mt-3 flex flex-col gap-3">
                            @foreach ($guide['agent']['auto'] as $line)
                                <li class="flex gap-3 text-[15px] leading-[1.6] text-body">
                                    <span class="mt-[9px] size-1.5 shrink-0 rounded-full bg-brick" aria-hidden="true"></span>
                                    <span>{{ $line }}</span>
                                </li>
                            @endforeach
                        </ul>

                        <p class="mt-7 text-sm font-bold tracking-[.14em] text-muted uppercase">Jak s ním pracovat</p>
                        <ol class="mt-3 flex flex-col gap-3">
                            @foreach ($guide['agent']['how'] as $line)
                                <li class="flex gap-3 text-[15px] leading-[1.6] text-body">
                                    <span class="shrink-0 font-bold text-brick tabular-nums">{{ $loop->iteration }}.</span>
                                    <span>{{ $line }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                </div>

                <h3 class="mt-12 text-h3-sm font-extrabold tracking-[-.02em] text-ink">S čím ti pomůže, když mu napíšeš</h3>
                <p class="mt-2 text-[15px] leading-[1.55] text-muted">Zadání můžeš psát přesně takhle. Agent si dohledá klienta, kartu i čísla sám.</p>

                <ul class="mt-6 grid gap-4 menu:grid-cols-3">
                    @foreach ($guide['agent']['asks'] as $ask)
                        <li class="flex flex-col rounded-card border border-ink/14 p-5">
                            <p class="font-bold text-ink">{{ $ask['title'] }}</p>
                            <p class="mt-3 rounded-2xl bg-ink px-4 py-3 text-[14px] leading-[1.5] text-cream">
                                <span class="text-brick" aria-hidden="true">›</span> {{ $ask['prompt'] }}
                            </p>
                            <p class="mt-3 text-[14px] leading-[1.55] text-body">{{ $ask['result'] }}</p>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-12 grid gap-6 menu:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
                    <figure class="rounded-card bg-ink p-6 text-cream menu:p-8">
                        <figcaption class="text-sm font-bold tracking-[.14em] text-cream/50 uppercase">Příklad</figcaption>

                        <div class="mt-5 ml-auto max-w-[46ch] rounded-2xl rounded-br-md bg-cream px-4 py-3 text-[14px] leading-[1.55] text-ink">
                            <p class="mb-1 text-xs font-bold text-muted">Ty</p>
                            {{ $guide['agent']['example']['you'] }}
                        </div>

                        <div class="mt-4 max-w-[52ch] rounded-2xl rounded-bl-md bg-ink-lift px-4 py-3 text-[14px] leading-[1.55]">
                            <p class="mb-1 text-xs font-bold text-brick">Agent</p>
                            <p>{{ $guide['agent']['example']['agent_lead'] }}</p>
                            <ul class="mt-2 flex flex-col gap-1.5">
                                @foreach ($guide['agent']['example']['agent'] as $line)
                                    <li class="flex gap-2.5 text-cream/85">
                                        <span class="mt-[8px] size-1.5 shrink-0 rounded-full bg-brick" aria-hidden="true"></span>
                                        <span>{{ $line }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </figure>

                    <div class="rounded-card border border-ink/14 p-6 menu:p-8">
                        <p class="text-sm font-bold tracking-[.14em] text-muted uppercase">Co nikdy neudělá</p>
                        <ul class="mt-4 flex flex-col gap-3">
                            @foreach ($guide['agent']['never'] as $line)
                                <li class="flex gap-3 text-[15px] leading-[1.6] text-body">
                                    <span class="shrink-0 font-bold text-brick" aria-hidden="true">✕</span>
                                    <span>{{ $line }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                <div class="mt-14 border-t border-ink/14 pt-12 menu:mt-16 menu:pt-14">
                    <p class="text-sm font-bold tracking-[.14em] text-brick uppercase">Nově u agenta</p>
                    <h3 class="mt-2 text-h3-sm font-extrabold tracking-[-.02em] text-ink">Práce z ClickUpu, zveřejnění jedním klikem a hlídání předplatného</h3>

                    <div class="mt-10 flex flex-col gap-16 menu:gap-20">
                        @foreach ($guide['agent']['news'] as $news)
                            <section id="{{ $news['id'] }}" class="scroll-mt-6" aria-labelledby="{{ $news['id'] }}-nadpis">
                                <div class="grid gap-8 menu:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)] menu:gap-14">
                                    <div>
                                        <p class="text-sm font-bold tracking-[.14em] text-muted uppercase tabular-nums">Novinka {{ $loop->iteration }}</p>
                                        <h4 id="{{ $news['id'] }}-nadpis" class="mt-2 text-h3-sm font-extrabold tracking-[-.02em] text-ink">{{ $news['title'] }}</h4>
                                        <p class="mt-4 text-[15px] leading-[1.6] text-body">{{ $news['intro'] }}</p>

                                        <p class="mt-7 text-sm font-bold tracking-[.14em] text-muted uppercase">Kde to najdeš</p>
                                        <ol class="mt-3 flex flex-wrap items-center gap-x-1.5 gap-y-2" aria-label="Kde to najdeš">
                                            @foreach ($news['where'] as $place)
                                                <li class="flex items-center gap-1.5">
                                                    <span class="rounded-pill bg-ink px-3.5 py-1.5 text-sm font-semibold text-cream">{{ $place }}</span>
                                                    @unless ($loop->last)
                                                        <span class="text-muted" aria-hidden="true">›</span>
                                                    @endunless
                                                </li>
                                            @endforeach
                                        </ol>
                                    </div>

                                    <div>
                                        <p class="text-sm font-bold tracking-[.14em] text-muted uppercase">Jak s tím pracovat</p>
                                        <ul class="mt-3 flex flex-col gap-3">
                                            @foreach ($news['how'] as $line)
                                                <li class="flex gap-3 text-[15px] leading-[1.6] text-body">
                                                    <span class="mt-[9px] size-1.5 shrink-0 rounded-full bg-brick" aria-hidden="true"></span>
                                                    <span>{{ $line }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>

                                @isset($news['thread'])
                                    <figure class="mt-8 max-w-[680px] rounded-media border border-ink/14 bg-white p-5 shadow-[0_24px_50px_-30px_rgb(19_17_16/.45)] menu:mt-10 menu:p-7">
                                        <figcaption class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-b border-ink/10 pb-4">
                                            <span class="font-bold text-ink">{{ $news['thread']['task'] }}</span>
                                            <span class="text-xs font-semibold text-muted">Ukázka komentářů v ClickUpu</span>
                                        </figcaption>

                                        <ol class="mt-5 flex flex-col gap-4">
                                            @foreach ($news['thread']['messages'] as $message)
                                                <li @class(['flex gap-3', 'ml-5 border-l-2 border-ink/10 pl-4 menu:ml-11' => ! $loop->first])>
                                                    <span @class([
                                                        'grid size-8 shrink-0 place-items-center rounded-full text-sm font-bold text-cream',
                                                        'bg-brick' => $message['agent'],
                                                        'bg-ink' => ! $message['agent'],
                                                    ]) aria-hidden="true">{{ mb_substr($message['author'], 0, 1) }}</span>
                                                    <div class="min-w-0 text-[14px] leading-[1.55] text-body">
                                                        <p class="font-bold text-ink">{{ $message['author'] }}</p>
                                                        @foreach ($message['lines'] as $line)
                                                            <p @class(['mt-1.5' => ! $loop->first])>{{ $line }}</p>
                                                        @endforeach
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ol>
                                    </figure>
                                @endisset

                                @isset($news['images'])
                                    <div @class([
                                        'mt-8 grid items-start gap-6 menu:mt-10',
                                        'menu:grid-cols-2' => count($news['images']) > 1,
                                    ])>
                                        @foreach ($news['images'] as $image)
                                            <figure @class(['menu:col-span-2' => count($news['images']) > 1 && $loop->first])>
                                                <a href="{{ $image['src'] }}" target="_blank" rel="noopener"
                                                   style="max-width: {{ $image['width'] }}px"
                                                   class="block overflow-hidden rounded-media border border-ink/14 bg-white shadow-[0_24px_50px_-30px_rgb(19_17_16/.45)] transition duration-300 ease-tavo hover:-translate-y-0.5">
                                                    <img src="{{ $image['src'] }}" alt="{{ $image['alt'] }}"
                                                         width="{{ $image['width'] }}" height="{{ $image['height'] }}"
                                                         loading="lazy" decoding="async" class="block h-auto w-full">
                                                </a>
                                                <figcaption class="mt-3 text-sm text-muted">{{ $image['caption'] }}</figcaption>
                                            </figure>
                                        @endforeach
                                    </div>
                                @endisset
                            </section>
                        @endforeach
                    </div>
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
                <a href="#{{ $guide['agent']['id'] }}"
                   class="rounded-pill border-[1.5px] border-brick bg-brick px-4 py-2 text-sm font-semibold text-cream transition duration-300 ease-tavo hover:bg-brick-dark">
                    Agent
                </a>
                @foreach ($guide['agent']['news'] as $news)
                    <a href="#{{ $news['id'] }}"
                       class="rounded-pill border-[1.5px] border-brick px-4 py-2 text-sm font-semibold text-brick transition duration-300 ease-tavo hover:bg-brick hover:text-cream">
                        {{ $news['label'] }}
                    </a>
                @endforeach
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
