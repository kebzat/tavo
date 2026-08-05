<x-layout.app
    :title="$case->seo_title ?: $case->title"
    :description="$case->seo_description ?: $case->excerpt"
    :schema="$schema">

    @php($hasGallery = $gallery->isNotEmpty())

    <header class="section-x pt-[140px] pb-[clamp(20px,4vw,50px)]">
        <div class="container-tavo">
            <a href="{{ route('cases.index') }}" data-reveal
               class="mb-[30px] inline-flex items-center gap-2 text-[13px] font-semibold tracking-[.12em] text-muted uppercase">
                ← Všechny reference
            </a>

            {{-- S galerií je hero dvousloupcový (text + slider), bez ní jen text. --}}
            <div class="grid grid-cols-1 items-center gap-[clamp(36px,5vw,72px)] {{ $hasGallery ? 'menu:grid-cols-[1.05fr_0.95fr]' : '' }}">
                <div>
                    <div data-reveal class="mb-6 flex flex-wrap gap-2.5">
                        @if ($case->category)
                            <x-tag tone="dark" size="xs">{{ $case->category->name }}</x-tag>
                        @endif
                        @if ($case->eyebrow)
                            <x-tag size="xs">{{ $case->eyebrow }}</x-tag>
                        @endif
                    </div>

                    {{-- Vedle galerie musí být nadpis menší, ať se do sloupce vejde. --}}
                    <h1 data-reveal
                        class="{{ $hasGallery ? 'text-[clamp(32px,4.4vw,68px)] leading-[1.02]' : 'text-case-title' }} m-0 max-w-[15ch] font-extrabold tracking-[-.03em]">
                        {{ $case->hero_headline ?: $case->title }}
                        @if ($case->hero_headline_accent)
                            <span class="text-brick italic">{{ $case->hero_headline_accent }}</span>
                        @endif
                    </h1>

                    @if ($case->hero_perex ?: $case->excerpt)
                        <p data-reveal class="text-lead mt-[30px] mb-0 max-w-[52ch] text-body">
                            {{ $case->hero_perex ?: $case->excerpt }}
                        </p>
                    @endif
                </div>

                @if ($hasGallery)
                    <div data-reveal>
                        <x-gallery :images="$gallery" />
                    </div>
                @endif
            </div>
        </div>
    </header>

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

    {{-- Spodek detailu si skládá správce sám. Odsazení i podmínku zobrazení
         řeší každý blok ve své komponentě, viz components/blocks/. --}}
    @foreach ($blocks as $block)
        <x-dynamic-component :component="$block['component']" :data="$block['data']" />
    @endforeach

    @if ($next)
        <section class="section-x bg-ink text-cream pt-[clamp(50px,6vw,80px)] pb-[clamp(70px,9vw,120px)]">
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
