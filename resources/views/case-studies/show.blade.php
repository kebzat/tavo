<x-layout.app
    :title="$case->seo_title ?: $case->title"
    :description="$case->seo_description ?: $case->excerpt"
    :schema="$schema">

    <header class="section-x pt-[140px]">
        <div class="container-tavo">
            <a href="{{ route('cases.index') }}" data-reveal
               class="mb-[30px] inline-flex items-center gap-2 text-[13px] font-semibold tracking-[.12em] text-muted uppercase">
                ← Všechny reference
            </a>

            <div data-reveal class="mb-6 flex flex-wrap gap-2.5">
                @if ($case->category)
                    <x-tag tone="dark" size="xs">{{ $case->category->name }}</x-tag>
                @endif
                @if ($case->eyebrow)
                    <x-tag size="xs">{{ $case->eyebrow }}</x-tag>
                @endif
            </div>

            {{-- Když nadpis detailu není vyplněný, použije se název reference. --}}
            <h1 data-reveal class="text-case-title m-0 max-w-[15ch] font-extrabold tracking-[-.03em]">
                {{ $case->hero_headline ?: $case->title }}
                @if ($case->hero_headline_accent)
                    <span class="text-brick italic">{{ $case->hero_headline_accent }}</span>
                @endif
            </h1>

            @if ($case->hero_perex ?: $case->excerpt)
                <p data-reveal class="text-lead mt-[30px] mb-[50px] max-w-[56ch] text-body">
                    {{ $case->hero_perex ?: $case->excerpt }}
                </p>
            @endif
        </div>
    </header>

    {{-- Galerie je nepovinná: bez obrázků se sekce vůbec nevykreslí. --}}
    @if ($gallery->isNotEmpty())
        <section class="section-x">
            <div class="container-tavo">
                <x-gallery :images="$gallery" />
            </div>
        </section>
    @endif

    <section class="section-x py-[clamp(50px,6vw,80px)]">
        <div class="container-tavo grid grid-cols-2 gap-6 border-y border-ink/14 py-[34px] menu:grid-cols-4">
            @foreach ([
                'Klient' => $case->client,
                'Obor' => $case->industry,
                'Rozsah' => $case->scope,
                'Doba' => $case->duration,
            ] as $label => $value)
                @if ($value)
                    <div data-reveal>
                        <div class="mb-2.5 text-xs font-bold tracking-[.12em] text-muted uppercase">{{ $label }}</div>
                        <div class="text-[17px] font-bold">{{ $value }}</div>
                    </div>
                @endif
            @endforeach
        </div>
    </section>

    @if ($case->problem_text)
        <section class="section-x pb-[clamp(50px,6vw,80px)]">
            <div class="container-tavo grid grid-cols-1 items-start gap-[clamp(30px,5vw,90px)] menu:grid-cols-[0.85fr_1.15fr]">
                <h2 data-reveal class="text-h2-sm m-0 font-extrabold tracking-[-.02em]">{{ $case->problem_title }}</h2>

                <div data-reveal>
                    <p class="text-body-lg mt-0 mb-5 text-body">{{ $case->problem_text }}</p>

                    @if ($case->problem_points)
                        <ul class="m-0 flex list-none flex-col gap-3 p-0">
                            @foreach ($case->problem_points as $point)
                                <li class="flex gap-3 text-base leading-[1.45] text-body">
                                    <span class="font-bold text-brick">—</span>{{ $point }}
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </section>
    @endif

    @if ($case->marketing_items || $case->dev_items)
        <section class="section-x section-y-sm bg-ink text-cream">
            <div class="container-tavo">
                <h2 data-reveal class="text-h2 mt-0 mb-3 font-extrabold tracking-[-.02em]">{{ $case->roles_title }}</h2>
                <p data-reveal class="text-perex mt-0 mb-[50px] max-w-[48ch] text-cream/65">{{ $case->roles_perex }}</p>

                {{-- U sólo projektů je vyplněná jen jedna role, ať pak karta nevisí v půlce pruhu. --}}
                <div class="grid grid-cols-1 gap-0.5 overflow-hidden rounded-[18px] bg-cream/15 {{ $case->hasBothRoles() ? 'menu:grid-cols-2' : 'menu:grid-cols-1' }}">
                    @foreach ([
                        ['title' => $case->marketing_title, 'items' => $case->marketing_items],
                        ['title' => $case->dev_title, 'items' => $case->dev_items],
                    ] as $role)
                        @if ($role['items'])
                            <div data-reveal class="bg-ink px-[clamp(26px,2.6vw,40px)] py-[clamp(30px,3vw,46px)]">
                                <div class="mb-5 text-[13px] font-bold tracking-[.12em] text-brick uppercase">{{ $role['title'] }}</div>
                                <ul class="m-0 flex list-none flex-col gap-4 p-0">
                                    @foreach ($role['items'] as $item)
                                        <li class="text-base leading-[1.5] text-cream/82">{{ $item }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    @endforeach
                </div>

                @if ($case->disclaimerPlacement() === 'roles')
                    <p data-reveal class="mt-9 mb-0 max-w-[80ch] text-[13px] leading-[1.6] text-cream/50">
                        {{ $case->disclaimer }}
                    </p>
                @endif
            </div>
        </section>
    @endif

    @if ($case->results)
        <section class="section-x section-y-sm bg-brick text-ink">
            <div class="container-tavo">
                <x-eyebrow data-reveal tone="ink" class="mb-10">Výsledek</x-eyebrow>

                <div class="grid grid-cols-1 gap-[30px] menu:grid-cols-3">
                    @foreach ($case->results as $result)
                        <div data-reveal class="border-t-2 border-ink pt-[22px]">
                            <div class="text-metric-lg font-extrabold tracking-[-.04em]">{{ $result['value'] }}</div>
                            <div class="mt-3 text-base text-ink/80">{{ $result['label'] }}</div>
                        </div>
                    @endforeach
                </div>

                @if ($case->disclaimer)
                    <p data-reveal class="mt-10 mb-0 text-[13px] text-ink/60">{{ $case->disclaimer }}</p>
                @endif
            </div>
        </section>
    @endif

    {{-- Když chybí metriky i role, nemá se poznámka kam schovat a dostane vlastní pruh. --}}
    @if ($case->disclaimerPlacement() === 'standalone')
        <section class="section-x bg-cream py-[clamp(40px,5vw,70px)]">
            <div class="container-tavo">
                <p data-reveal class="m-0 max-w-[70ch] text-[13px] leading-[1.6] text-muted">
                    {{ $case->disclaimer }}
                </p>
            </div>
        </section>
    @endif

    @if ($case->quote)
        <section class="section-x section-y-sm bg-cream">
            <div class="container-tavo max-w-[1100px] text-center">
                <blockquote data-reveal class="text-quote m-0 font-semibold tracking-[-.02em] text-ink">{{ $case->quote }}</blockquote>
                @if ($case->quote_author)
                    <div data-reveal class="mt-7 text-[15px] font-bold text-muted">{{ $case->quote_author }}</div>
                @endif
            </div>
        </section>
    @endif

    @if ($next)
        <section class="section-x section-y-sm bg-ink text-cream">
            <div class="container-tavo">
                <div class="mb-9 flex flex-wrap items-end justify-between gap-5">
                    <h2 data-reveal class="text-h2-sm m-0 font-extrabold tracking-[-.02em]">Další projekt</h2>
                    <a data-reveal href="{{ route('cases.index') }}"
                       class="border-b-2 border-brick pb-[3px] text-[15px] font-bold text-cream">Všechny reference →</a>
                </div>

                <a href="{{ route('cases.show', $next->slug) }}" data-reveal
                   class="grid grid-cols-1 items-center gap-[clamp(24px,3vw,50px)] overflow-hidden rounded-card bg-ink-soft transition-transform duration-500 ease-tavo hover:-translate-y-1.5 menu:grid-cols-[1fr_1.3fr]">
                    <x-media
                        :url="$next->thumbUrl()"
                        :alt="$next->imageAlt()"
                        :label="$next->thumb_label"
                        tone="dark"
                        radius="rounded-none" />

                    <div class="px-[clamp(26px,3vw,44px)] pb-8 menu:pb-0">
                        <div class="mb-3.5 text-xs font-bold tracking-[.1em] text-brick uppercase">{{ $next->eyebrow }}</div>
                        <h3 class="text-card mt-0 mb-3 font-extrabold tracking-[-.02em]">{{ $next->title }}</h3>
                        <span class="text-[15px] font-bold text-cream">Otevřít projekt →</span>
                    </div>
                </a>
            </div>
        </section>
    @endif

    <x-cta-band
        title="Chcete něco podobného?"
        secondary-label="Napsat nám"
        :secondary-url="route('home').'#kontakt'" />
</x-layout.app>
