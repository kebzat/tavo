{{--
    Potenciální spolupráce: dopadová stránka pro firmu, kterou chceme získat.
    Pořadí: co jsme objevili → co doporučujeme → akční kroky → jak přemýšlíme.
    Mezi sekce se vkládají ukázky (tmavé pruhy), viz x-proposal.example.
    Prázdná sekce se nevykreslí. Data připravuje App\Models\Proposal.
--}}
<x-layout.document :title="$proposal->title" :eyebrow="$proposal->company_name">

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
                <div class="menu:sticky menu:top-8 menu:self-start">
                    <p class="text-sm font-bold tracking-[.14em] text-brick tabular-nums">01</p>
                    <h2 data-reveal class="mt-3 text-h2 font-extrabold tracking-[-.02em]">
                        {{ text('spoluprace.findings_title', 'Co jsme objevili') }}
                    </h2>
                    @if ($proposal->findings_intro)
                        <p data-reveal class="mt-5 max-w-[46ch] whitespace-pre-line text-perex text-body">{{ $proposal->findings_intro }}</p>
                    @endif
                </div>

                <ol class="border-b border-ink/14">
                    @foreach ($findings as $finding)
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
        </section>
    @endif

    @foreach ($examples['after_findings'] as $example)
        <x-proposal.example :example="$example" />
    @endforeach

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
                <div class="menu:sticky menu:top-8 menu:self-start">
                    <p class="text-sm font-bold tracking-[.14em] text-brick tabular-nums">03</p>
                    <h2 data-reveal class="mt-3 text-h2 font-extrabold tracking-[-.02em]">
                        {{ text('spoluprace.steps_title', 'Akční kroky v prvních týdnech') }}
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

    @if ($principles)
        <section class="section-x section-y bg-ink text-cream" data-block-bg="ink">
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

    <x-cta-band
        id="kontakt"
        :eyebrow="text('spoluprace.cta_eyebrow', 'Další krok')"
        :title="text('spoluprace.cta_title', 'Probereme to spolu?')"
        :perex="text('spoluprace.cta_perex', 'Za půl hodiny projdeme, co z toho dává smysl právě vám a v jakém pořadí. Co smysl nedává, vyškrtneme.')"
    />
</x-layout.document>
