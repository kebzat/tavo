{{--
    Potenciální spolupráce: dopadová stránka pro firmu, kterou chceme získat.
    Pořadí: co jsme objevili → co doporučujeme → akční kroky → měsíční spolupráce → jak přemýšlíme → ukázky na konec.
    Mezi sekce se vkládají ukázky (tmavé pruhy), viz x-proposal.example.
    Prázdná sekce se nevykreslí. Data připravuje App\Models\Proposal.
--}}
<x-layout.document :title="$proposal->title" :eyebrow="$proposal->company_name">

    <x-proposal.section-nav :nav="$nav" :company="$proposal->company_name" />

    <section class="section-x pt-6 pb-2">
        <div class="container-tavo">
            <div class="rounded-card bg-ink px-[7vw] py-12 text-cream menu:px-14 menu:py-16">
                <div class="flex flex-col gap-10 loop:flex-row loop:items-end loop:justify-between">
                    <div class="max-w-[64ch]">
                        <p class="mb-5 text-sm font-bold tracking-[.14em] text-brick uppercase">
                            {{ text('spoluprace.eyebrow', 'Potenciální spolupráce') }} · {{ $proposal->company_name }}
                        </p>

                        <h1 data-line class="text-h2-lg max-w-[20ch] font-extrabold tracking-[-.03em]">
                            {{ $proposal->title }}
                        </h1>

                        @if ($proposal->intro)
                            <p class="mt-6 whitespace-pre-line text-perex text-cream/70">{{ $proposal->intro }}</p>
                        @endif

                        {{-- Rozcestník. Konec úvodu hlídá lepivé menu: jakmile zmizí z obrazovky, menu nastoupí. --}}
                        @if ($nav)
                            <ul data-section-nav-start class="mt-8 flex flex-wrap gap-2.5">
                                @foreach ($nav as $item)
                                    <li>
                                        <a href="#{{ $item['id'] }}"
                                           class="group inline-flex items-center gap-2 rounded-pill border-[1.5px] border-cream/30 px-5 py-3 text-sm font-bold text-cream transition duration-300 ease-tavo hover:-translate-y-0.5 hover:border-cream hover:bg-cream hover:text-ink">
                                            {{ $item['label'] }}
                                            <span aria-hidden="true" class="transition-transform duration-300 ease-tavo group-hover:translate-y-0.5">↓</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <x-client-docs.links :links="$links" class="mt-8" />
                    </div>

                    <div class="shrink-0 loop:text-right">
                        <p class="text-sm font-semibold text-cream/60">{{ text('spoluprace.prepared_by', 'Připravili Pavel a Tom') }}</p>
                        @if ($proposal->prepared_at)
                            <p class="mt-1 text-metric-sm font-extrabold tracking-[-.02em] text-brick tabular-nums">
                                {{ $proposal->prepared_at->format('j. n. Y') }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>

    @if ($tiles)
        <section class="section-x pt-5">
            <div @class([
                'container-tavo grid grid-cols-2 gap-3 menu:gap-5',
                'menu:grid-cols-3' => count($tiles) === 3,
                'menu:grid-cols-4' => count($tiles) >= 4,
            ])>
                @foreach ($tiles as $tile)
                    <div class="rounded-card border border-ink/14 p-5 menu:p-7">
                        <p class="text-metric font-extrabold tracking-[-.03em] text-brick tabular-nums">{{ $tile['value'] }}</p>
                        @if ($tile['label'])
                            <p class="mt-2 text-sm leading-snug text-muted">{{ $tile['label'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- 01 Co jsme objevili --}}
    @if ($findings)
        <section id="zjisteni" class="section-x section-y">
            <div class="container-tavo grid gap-10 menu:grid-cols-[0.8fr_1.2fr] loop:gap-20">
                <div class="menu:sticky menu:top-30 menu:self-start">
                    <p class="text-sm font-bold tracking-[.14em] text-brick tabular-nums">01</p>
                    <h2 data-reveal class="mt-3 text-h2 font-extrabold tracking-[-.02em]">
                        {{ $proposal->findings_title ?: text('spoluprace.findings_title', 'Co jsme objevili') }}
                    </h2>
                    @if ($proposal->findings_intro)
                        <p data-reveal class="mt-5 max-w-[46ch] whitespace-pre-line text-perex text-body">{{ $proposal->findings_intro }}</p>
                    @endif
                </div>

                {{-- Skupiny podle naléhavosti. Bez vyplněné naléhavosti je tu jedna skupina bez nadpisu. --}}
                <div class="grid gap-12 menu:gap-16">
                    @foreach ($findings as $group)
                        <div>
                            @if ($group['label'])
                                <p data-reveal class="mb-4 flex items-center gap-3 text-sm font-bold tracking-[.14em] text-brick uppercase">
                                    {{ $group['label'] }}
                                    <span class="text-muted tabular-nums">{{ count($group['items']) }}</span>
                                </p>
                            @endif
                            <ol class="border-b border-ink/14">
                                @foreach ($group['items'] as $finding)
                                    <li data-reveal class="border-t border-ink/14 py-7 menu:py-9">
                                        @if ($finding['tag'])
                                            <span class="audit-tag {{ $finding['tag']['class'] }}">{{ $finding['tag']['label'] }}</span>
                                        @endif
                                        <h3 class="mt-3 text-step font-extrabold tracking-[-.01em] text-ink">{{ $finding['title'] }}</h3>
                                        @if ($finding['body'])
                                            <p class="mt-3 max-w-[62ch] whitespace-pre-line text-body-lg text-body">{{ $finding['body'] }}</p>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Kotva „Redesign webu“: návrh homepage a hned za ním srovnání kdysi a dnes. --}}
    <div id="redesign">
    @foreach ($examples['after_findings'] as $example)
        <x-proposal.example :example="$example" />
    @endforeach

    {{-- Kdysi, dnes a s námi: tři podoby webu vedle sebe, poslední zvýrazněná.
         Stojí za ukázkou návrhu, ať klient nejdřív vidí nový design a pak srovnání. --}}
    @if ($timeline)
        {{-- Spodní odsazení nechává na sekci pod sebou, ať se dvě krémové sekce nesečtou. --}}
        <section id="kdysi-a-dnes" class="section-x section-y pb-0!">
            <div class="container-tavo">
                <div class="max-w-[60ch]">
                    <h2 data-reveal class="text-h2 font-extrabold tracking-[-.02em]">
                        {{ text('spoluprace.timeline_title', 'Kdysi, dnes a s námi') }}
                    </h2>
                    @if ($proposal->timeline_intro)
                        <p data-reveal class="mt-5 whitespace-pre-line text-perex text-body">{{ $proposal->timeline_intro }}</p>
                    @endif
                </div>

                <ol @class([
                    'mt-12 grid gap-8 menu:gap-5 loop:gap-8',
                    'menu:grid-cols-2' => count($timeline) === 2,
                    'menu:grid-cols-3' => count($timeline) >= 3,
                ])>
                    @foreach ($timeline as $stage)
                        <li data-reveal class="flex flex-col">
                            <div class="flex items-baseline gap-3">
                                @if ($stage['label'])
                                    <span @class([
                                        'text-sm font-bold tracking-[.14em] uppercase tabular-nums',
                                        'text-brick' => $loop->last,
                                        'text-muted' => ! $loop->last,
                                    ])>{{ $stage['label'] }}</span>
                                @endif
                                <h3 class="text-h3-sm font-extrabold tracking-[-.02em] text-ink">{{ $stage['title'] }}</h3>
                            </div>

                            @if ($stage['image'])
                                <a href="{{ $stage['full_url'] }}" target="_blank" rel="noopener"
                                   @class([
                                       'group mt-5 block overflow-hidden rounded-card border bg-ink-soft transition duration-300 ease-tavo hover:-translate-y-1',
                                       'border-brick shadow-[0_24px_60px_-24px_rgba(219,75,36,.55)] outline-2 outline-brick' => $loop->last,
                                       'border-ink/14' => ! $loop->last,
                                   ])>
                                    <span aria-hidden="true" class="flex items-center gap-1.5 border-b border-cream/10 px-3.5 py-2.5">
                                        <span class="size-2 rounded-full bg-cream/25"></span>
                                        <span class="size-2 rounded-full bg-cream/25"></span>
                                        <span class="size-2 rounded-full bg-cream/25"></span>
                                    </span>
                                    <span class="block aspect-[9/5] overflow-hidden bg-cream">
                                        <img src="{{ $stage['image']['src'] }}"
                                             @if ($stage['image']['srcset']) srcset="{{ $stage['image']['srcset'] }}" sizes="(min-width: 861px) 30vw, 88vw" @endif
                                             alt="{{ $stage['image']['alt'] }}"
                                             @if ($stage['image']['width']) width="{{ $stage['image']['width'] }}" @endif
                                             @if ($stage['image']['height']) height="{{ $stage['image']['height'] }}" @endif
                                             loading="lazy" decoding="async"
                                             class="h-full w-full object-cover object-top transition-transform duration-500 ease-tavo group-hover:scale-[1.02]">
                                    </span>
                                </a>
                            @endif

                            @if ($stage['body'])
                                <p class="mt-4 whitespace-pre-line text-perex text-body">{{ $stage['body'] }}</p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif
    </div>

    {{-- 02 Co doporučujeme --}}
    @if ($recommendations)
        <section id="doporuceni" class="section-x section-y">
            <div class="container-tavo">
                <div class="max-w-[60ch]">
                    <p class="text-sm font-bold tracking-[.14em] text-brick tabular-nums">02</p>
                    <h2 data-reveal class="mt-3 text-h2 font-extrabold tracking-[-.02em]">
                        {{ text('spoluprace.recommendations_title', 'Co doporučujeme') }}
                    </h2>
                    @if ($proposal->recommendations_intro)
                        <p data-reveal class="mt-5 whitespace-pre-line text-perex text-body">{{ $proposal->recommendations_intro }}</p>
                    @endif
                </div>

                <div class="mt-12 grid gap-4 menu:grid-cols-2 menu:gap-5">
                    @foreach ($recommendations as $recommendation)
                        <article data-reveal class="flex flex-col rounded-card border border-ink/14 p-7 menu:p-9">
                            <div class="flex items-baseline gap-4">
                                <span class="text-sm font-bold text-brick tabular-nums">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                <h3 class="text-h3-sm font-extrabold tracking-[-.02em] text-ink">{{ $recommendation['title'] }}</h3>
                            </div>
                            @if ($recommendation['body'])
                                <p class="mt-4 flex-1 whitespace-pre-line text-perex text-body">{{ $recommendation['body'] }}</p>
                            @endif
                            @if ($recommendation['who'])
                                <p class="mt-6 w-fit rounded-pill bg-ink/6 px-3.5 py-1.5 text-xs font-bold text-ink">
                                    {{ $recommendation['who'] }}
                                </p>
                            @endif
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @foreach ($examples['after_recommendations'] as $example)
        <x-proposal.example :example="$example" />
    @endforeach

    {{-- 03 Akční kroky --}}
    @if ($steps['now'] || $steps['later'])
        <section id="kroky" class="section-x section-y">
            <div class="container-tavo grid gap-10 menu:grid-cols-[0.8fr_1.2fr] loop:gap-20">
                <div class="menu:sticky menu:top-30 menu:self-start">
                    <p class="text-sm font-bold tracking-[.14em] text-brick tabular-nums">03</p>
                    <h2 data-reveal class="mt-3 text-h2 font-extrabold tracking-[-.02em]">
                        {{ $proposal->steps_title ?: text('spoluprace.steps_title', 'Akční kroky v prvních týdnech') }}
                    </h2>
                    @if ($proposal->steps_intro)
                        <p data-reveal class="mt-5 max-w-[46ch] whitespace-pre-line text-perex text-body">{{ $proposal->steps_intro }}</p>
                    @endif
                </div>

                <div>
                    @if ($steps['now'])
                        <ol class="border-b border-ink/14">
                            @foreach ($steps['now'] as $step)
                                <li data-reveal class="grid gap-2 border-t border-ink/14 py-7 sm:grid-cols-[150px_1fr] sm:gap-8">
                                    <p class="text-base font-extrabold tracking-[-.01em] text-brick">{{ $step['when'] }}</p>
                                    <div>
                                        <h3 class="text-step font-extrabold tracking-[-.01em] text-ink">{{ $step['title'] }}</h3>
                                        @if ($step['body'])
                                            <p class="mt-2.5 max-w-[60ch] whitespace-pre-line text-perex text-body">{{ $step['body'] }}</p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif

                    @if ($steps['later'])
                        <p @class(['text-xs font-bold tracking-[.14em] text-muted uppercase', 'mt-14' => $steps['now']])>
                            {{ text('spoluprace.later_title', 'Potom postupně') }}
                        </p>
                        <ol class="mt-4 flex flex-col gap-3">
                            @foreach ($steps['later'] as $step)
                                <li data-reveal class="grid gap-2 rounded-card bg-ink/5 p-6 sm:grid-cols-[150px_1fr] sm:gap-8 menu:p-7">
                                    <p class="text-base font-extrabold tracking-[-.01em] text-muted">{{ $step['when'] }}</p>
                                    <div>
                                        <h3 class="text-step font-extrabold tracking-[-.01em] text-ink">{{ $step['title'] }}</h3>
                                        @if ($step['body'])
                                            <p class="mt-2.5 max-w-[60ch] whitespace-pre-line text-perex text-body">{{ $step['body'] }}</p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            </div>
        </section>
    @endif

    @foreach ($examples['after_steps'] as $example)
        <x-proposal.example :example="$example" />
    @endforeach

    {{-- 04 Měsíční spolupráce: varianty vedle sebe, doporučená je tmavá. --}}
    @if ($packages)
        <section id="spoluprace" class="section-x section-y">
            <div class="container-tavo">
                <div class="max-w-[60ch]">
                    <p class="text-sm font-bold tracking-[.14em] text-brick tabular-nums">04</p>
                    <h2 data-reveal class="mt-3 text-h2 font-extrabold tracking-[-.02em]">
                        {{ text('spoluprace.packages_title', 'Měsíční spolupráce') }}
                    </h2>
                    @if ($proposal->packages_intro)
                        <p data-reveal class="mt-5 whitespace-pre-line text-perex text-body">{{ $proposal->packages_intro }}</p>
                    @endif
                </div>

                <div @class([
                    'mt-12 grid items-stretch gap-4 menu:gap-5',
                    'menu:grid-cols-2' => count($packages) === 2,
                    'menu:grid-cols-3' => count($packages) >= 3,
                ])>
                    @foreach ($packages as $package)
                        <article data-reveal @class([
                            'relative flex flex-col rounded-card p-7 menu:p-9',
                            'bg-ink text-cream shadow-[0_24px_60px_-24px_rgba(219,75,36,.55)] outline-2 outline-brick' => $package['recommended'],
                            'border border-ink/14 text-ink' => ! $package['recommended'],
                        ])>
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <h3 class="text-h3-sm font-extrabold tracking-[-.02em]">{{ $package['title'] }}</h3>
                                @if ($package['recommended'])
                                    <span class="rounded-pill bg-brick px-3.5 py-1.5 text-xs font-bold text-cream">
                                        {{ text('spoluprace.packages_recommended', 'Doporučujeme') }}
                                    </span>
                                @endif
                            </div>

                            @if ($package['price'])
                                <p class="mt-6 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                    <span class="text-metric font-extrabold tracking-[-.03em] tabular-nums">{{ $package['price'] }}</span>
                                    @if ($package['period'])
                                        <span @class(['text-sm font-semibold', 'text-cream/60' => $package['recommended'], 'text-muted' => ! $package['recommended']])>{{ $package['period'] }}</span>
                                    @endif
                                </p>
                            @endif

                            @if ($package['scope'])
                                <p @class([
                                    'mt-4 w-fit rounded-pill px-3.5 py-1.5 text-sm font-bold',
                                    'bg-cream/10 text-cream' => $package['recommended'],
                                    'bg-ink/6 text-ink' => ! $package['recommended'],
                                ])>{{ $package['scope'] }}</p>
                            @endif

                            @if ($package['body'])
                                <p @class(['mt-5 whitespace-pre-line text-perex', 'text-cream/70' => $package['recommended'], 'text-body' => ! $package['recommended']])>{{ $package['body'] }}</p>
                            @endif

                            @if ($package['features'])
                                <ul @class(['mt-6 flex flex-1 flex-col gap-3 border-t pt-6', 'border-cream/15' => $package['recommended'], 'border-ink/14' => ! $package['recommended']])>
                                    @foreach ($package['features'] as $feature)
                                        <li class="flex gap-3 text-body-lg">
                                            <svg aria-hidden="true" class="mt-1 size-4.5 shrink-0 text-brick" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                                            <span @class(['text-cream/85' => $package['recommended'], 'text-body' => ! $package['recommended']])>{{ $feature }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Zkušenosti z praxe: věty s cihlově vyznačenými čísly. Čtou se jako
         podklad k tomu, jak přemýšlíme, proto stojí hned před zásadami. --}}
    @if ($experiences)
        <section id="zkusenosti" class="section-x section-y">
            <div class="container-tavo">
                <h2 data-reveal class="max-w-[22ch] text-h2 font-extrabold tracking-[-.02em]">
                    {{ text('spoluprace.experiences_title', 'Pár zkušeností z posledních měsíců') }}
                </h2>

                <ul class="mt-12 grid gap-3 menu:grid-cols-2 menu:gap-5">
                    @foreach ($experiences as $parts)
                        <li data-reveal class="flex gap-4 rounded-card border border-ink/14 p-6 menu:gap-5 menu:p-8">
                            <span class="pt-1 text-sm font-bold text-brick tabular-nums">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <p class="text-body-lg text-body">@foreach ($parts as $part)@if ($part['strong'])<strong @class(['font-extrabold text-brick', 'whitespace-nowrap' => $part['nowrap']])>{{ $part['text'] }}</strong>@else{{ $part['text'] }}@endif@endforeach</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    @if ($principles)
        <section id="pristup" class="section-x section-y bg-ink text-cream" data-block-bg="ink">
            <div class="container-tavo">
                <h2 data-reveal class="max-w-[18ch] text-h2 font-extrabold tracking-[-.02em]">
                    {{ text('spoluprace.principles_title', 'Jak k tomu přistupujeme') }}
                </h2>

                <div @class([
                    'mt-12 grid gap-8 menu:gap-10',
                    'menu:grid-cols-2' => count($principles) === 2 || count($principles) === 4,
                    'menu:grid-cols-3' => count($principles) === 3,
                ])>
                    @foreach ($principles as $principle)
                        <div data-reveal class="border-t border-cream/15 pt-6">
                            <h3 class="text-step font-extrabold tracking-[-.01em]">{{ $principle['title'] }}</h3>
                            @if ($principle['body'])
                                <p class="mt-3 whitespace-pre-line text-perex text-cream/70">{{ $principle['body'] }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Za tmavými zásadami je ukázka světlá, jinak by se dva tmavé pruhy slily. --}}
    @foreach ($examples['after_principles'] as $example)
        <x-proposal.example :example="$example" :light="(bool) $principles" />
    @endforeach

    {{-- Společná fotka všech konceptů (nástroje → Koncepty: společná fotka). --}}
    @if ($teamPhoto)
        <section class="section-x section-y bg-cream text-ink" data-block-bg="cream">
            <div @class([
                'container-tavo grid grid-cols-1 items-center gap-[clamp(28px,5vw,80px)]',
                'menu:grid-cols-[1.1fr_0.9fr]' => $teamPhoto['title'] || $teamPhoto['text'],
            ])>
                <x-media data-reveal :image="$teamPhoto['image']" fit="natural" sizes="(min-width: 861px) 52vw, 88vw" />

                @if ($teamPhoto['title'] || $teamPhoto['text'])
                    <div data-reveal>
                        @if ($teamPhoto['title'])
                            <h2 class="text-h2 m-0 max-w-[16ch] font-extrabold tracking-[-.02em]">{{ $teamPhoto['title'] }}</h2>
                        @endif
                        @if ($teamPhoto['text'])
                            <p class="mt-6 mb-0 max-w-[48ch] whitespace-pre-line text-perex text-muted">{{ $teamPhoto['text'] }}</p>
                        @endif
                    </div>
                @endif
            </div>
        </section>
    @endif

    <x-cta-band
        id="kontakt"
        :eyebrow="text('spoluprace.cta_eyebrow', 'Další krok')"
        :title="text('spoluprace.cta_title', 'Probereme to spolu?')"
        :perex="text('spoluprace.cta_perex', 'Za půl hodiny projdeme, co z toho dává smysl právě vám a v jakém pořadí. Co smysl nedává, vyškrtneme.')"
    />
</x-layout.document>
