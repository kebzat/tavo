<x-layout.app
    title="Reference"
    description="Weby, e-shopy a reklamní účty, které máme za sebou. Weby a značky staví Tom, reklamu vede Pavel.">

    <header class="section-x pt-[150px] pb-[50px]">
        <div class="container-tavo">
            <a href="{{ route('home') }}" data-reveal
               class="mb-[30px] inline-flex items-center gap-2 text-[13px] font-semibold tracking-[.12em] text-muted uppercase">
                ← Zpět na úvod
            </a>
            <h1 data-reveal class="text-page-title m-0 max-w-[15ch] font-extrabold tracking-[-.03em]">
                Na čem jsme <span class="text-brick italic">dělali.</span>
            </h1>
            <p data-reveal class="text-perex mt-[34px] mb-0 max-w-[52ch] text-body">
                Výběr toho, co máme za sebou. Weby a značky staví Tom, reklamní účty vede Pavel.
                Čísla u nich neuvádíme, protože je nemáme od klientů ověřená.
            </p>
        </div>
    </header>

    <section class="section-x pb-2.5">
        <div data-reveal class="container-tavo flex flex-wrap gap-2.5">
            <a href="{{ route('cases.index') }}"
               class="rounded-pill px-4 py-[9px] text-[13px] font-bold transition {{ $activeSlug ? 'bg-ink/6 text-ink hover:bg-ink/12' : 'bg-ink text-cream' }}">
                Vše
            </a>
            @foreach ($categories as $category)
                <a href="{{ route('cases.index', ['kategorie' => $category->slug]) }}"
                   class="rounded-pill px-4 py-[9px] text-[13px] font-semibold transition {{ $activeSlug === $category->slug ? 'bg-ink text-cream' : 'bg-ink/6 text-ink hover:bg-ink/12' }}">
                    {{ $category->name }}
                </a>
            @endforeach
        </div>
    </section>

    <section class="section-x pt-10 pb-[clamp(70px,9vw,120px)]">
        <div class="container-tavo">
            @if ($cases->isEmpty())
                <p class="text-body-lg text-muted">V této kategorii zatím žádný projekt nemáme.</p>
            @else
                <div class="grid grid-cols-1 gap-[clamp(28px,3vw,48px)] menu:grid-cols-2">
                    @foreach ($cases as $case)
                        <a href="{{ route('cases.show', $case->slug) }}" data-reveal class="group block">
                            <div class="relative">
                                <x-media
                                    :url="$case->thumbUrl()"
                                    :alt="$case->imageAlt()"
                                    :label="$case->thumb_label"
                                    radius="rounded-thumb"
                                    class="transition-transform duration-500 ease-tavo group-hover:scale-[1.02]" />

                                @if ($case->category)
                                    <span class="absolute top-4 left-4 rounded-pill bg-ink px-3 py-1.5 text-[11px] font-bold tracking-[.1em] text-cream uppercase">
                                        {{ $case->category->name }}
                                    </span>
                                @endif
                            </div>

                            <div class="mt-5 flex items-start justify-between gap-4">
                                <div>
                                    <h2 class="text-h3-sm mt-0 mb-2 font-extrabold tracking-[-.02em] text-ink">{{ $case->title }}</h2>
                                    <p class="m-0 max-w-[42ch] text-[15px] leading-[1.5] text-muted">{{ $case->excerpt }}</p>
                                </div>
                                @if ($case->headline_metric)
                                    <span class="text-metric-sm font-extrabold tracking-[-.03em] whitespace-nowrap text-brick">
                                        {{ $case->headline_metric }}
                                    </span>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <x-cta-band
        title="Řekněte nám, co potřebujete."
        secondary-label="Co děláme"
        :secondary-url="route('home').'#sluzby'" />
</x-layout.app>
