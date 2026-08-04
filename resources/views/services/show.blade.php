<x-layout.app
    :title="$service->seo_title ?: $service->title"
    :description="$service->seo_description ?: $service->excerpt"
    :schema="$schema">

    <header class="section-x pt-[150px] pb-[50px]">
        <div class="container-tavo">
            <a href="{{ route('home') }}" data-reveal
               class="mb-[30px] flex w-fit items-center gap-2 text-[13px] font-semibold tracking-[.12em] text-muted uppercase">
                ← Zpět na úvod
            </a>

            @if ($service->hero_eyebrow)
                <x-eyebrow data-reveal :rule="true" class="mb-6">{{ $service->hero_eyebrow }}</x-eyebrow>
            @endif

            {{-- Když nadpis detailu není vyplněný, použije se název služby. --}}
            <h1 data-reveal class="text-page-title m-0 max-w-[15ch] font-extrabold tracking-[-.03em]">
                {{ $service->hero_headline ?: $service->title }}
                @if ($service->hero_headline_accent)
                    <span class="text-brick italic">{{ $service->hero_headline_accent }}</span>
                @endif
            </h1>

            @if ($service->hero_perex ?: $service->excerpt)
                <p data-reveal class="text-perex mt-[34px] mb-0 max-w-[52ch] text-body">
                    {{ $service->hero_perex ?: $service->excerpt }}
                </p>
            @endif
        </div>
    </header>

    @if ($service->target_groups)
        <section class="section-x section-y-sm bg-ink text-cream">
            <div class="container-tavo grid grid-cols-1 items-start gap-[clamp(30px,5vw,90px)] menu:grid-cols-[0.85fr_1.15fr]">
                <div>
                    <x-eyebrow data-reveal class="mb-5">Pro koho</x-eyebrow>
                    <h2 data-reveal class="text-h2-sm m-0 font-extrabold tracking-[-.02em]">{{ $service->target_group_title }}</h2>
                </div>

                <ul data-reveal class="m-0 flex list-none flex-col p-0">
                    @foreach ($service->target_groups as $group)
                        <li class="flex gap-[18px] border-t border-cream/15 py-4 text-base leading-[1.45] text-cream/82">
                            <span class="font-bold text-brick">—</span>{{ $group }}
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    @if ($service->offerings)
        <section class="section-x section-y bg-cream">
            <div class="container-tavo">
                <h2 data-reveal class="text-h2 mt-0 mb-[46px] font-extrabold tracking-[-.02em]">{{ $service->offerings_title }}</h2>

                <div class="grid grid-cols-1 gap-6 menu:grid-cols-2 loop:grid-cols-3">
                    @foreach ($service->offerings as $offering)
                        <div data-reveal class="rounded-card border border-ink/12 bg-cream p-8 transition-transform duration-500 ease-tavo hover:-translate-y-1.5">
                            <div class="mb-3 text-xl font-extrabold tracking-[-.01em]">{{ $offering['title'] }}</div>
                            <p class="m-0 text-[15px] leading-[1.55] text-muted">{{ $offering['text'] }}</p>
                        </div>
                    @endforeach

                    <div data-reveal class="rounded-card bg-ink p-8 text-cream">
                        <div class="mb-3 text-xl font-extrabold tracking-[-.01em]">Nevíte, co potřebujete?</div>
                        <p class="mt-0 mb-6 text-[15px] leading-[1.55] text-cream/70">
                            Napište nám pár vět o vašem podnikání. Řekneme rovnou, jestli dává smysl web, kampaň, nebo obojí.
                        </p>
                        <a href="{{ route('home') }}#kontakt" class="text-[15px] font-bold text-brick">Poptat projekt →</a>
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if ($service->process_steps)
        <section class="section-x section-y-sm bg-ink text-cream">
            <div class="container-tavo">
                <h2 data-reveal class="text-h2 mt-0 mb-[46px] font-extrabold tracking-[-.02em]">{{ $service->process_title }}</h2>

                <div class="grid grid-cols-2 gap-5 menu:grid-cols-4">
                    @foreach ($service->process_steps as $step)
                        <div data-reveal class="border-t-2 border-cream/30 pt-[22px]">
                            <div class="mb-3.5 text-[13px] font-bold text-brick">{{ $step['number'] }}</div>
                            <div class="text-step mb-2.5 font-extrabold tracking-[-.01em]">{{ $step['title'] }}</div>
                            <div class="text-sm leading-[1.5] text-cream/65">{{ $step['text'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($others->isNotEmpty())
        <section class="section-x section-y bg-cream">
            <div class="container-tavo">
                <h2 data-reveal class="text-h2 mt-0 mb-[46px] font-extrabold tracking-[-.02em]">Další oblasti</h2>

                <div>
                    @foreach ($others as $other)
                        <div data-svc @if ($other->url()) data-svc-link @endif
                             class="relative grid grid-cols-[40px_1fr_auto] items-center gap-[26px] border-t border-ink/14 py-[clamp(28px,3.4vw,44px)] menu:grid-cols-[80px_1fr_auto] @if ($loop->last) border-b @endif">
                            <span class="text-sm font-bold text-brick">{{ $other->number }}</span>
                            <div>
                                <div class="text-svc font-extrabold tracking-[-.02em]">{{ $other->title }}</div>
                                <div class="mt-3.5 max-w-[60ch] text-[15px] leading-[1.5] text-muted">
                                    {{ $other->excerpt }}
                                </div>
                                @if ($other->url())
                                    <span class="mt-4 inline-flex items-center gap-1.5 text-[15px] font-bold text-brick">
                                        Zjistit více
                                        <span data-svc-arrow class="transition-transform duration-300 ease-tavo">→</span>
                                    </span>
                                @endif
                            </div>
                            <span data-svc-plus class="text-[34px] font-light text-brick">+</span>

                            @if ($other->url())
                                <a href="{{ $other->url() }}"
                                   class="absolute inset-0 rounded-[2px] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brick"
                                   aria-label="{{ $other->title }} – zjistit více"></a>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <x-cta-band
        title="Pojďme to postavit."
        secondary-label="Poptat projekt"
        :secondary-url="route('home').'#kontakt'" />
</x-layout.app>
