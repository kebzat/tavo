@php
    $skupina = 'Výpis referencí';
@endphp

<x-layout.app
    :title="text('reference.seo_titulek', 'Reference', $skupina, 'Titulek stránky /reference v prohlížeči a ve vyhledávačích')"
    :description="text('reference.seo_popis', 'Weby, e-shopy a reklamní účty, které máme za sebou. Weby a značky staví Tom, reklamu vede Pavel.', $skupina, 'Popisek stránky /reference ve vyhledávačích')">

    <header class="section-x pt-[150px] pb-[50px]">
        <div class="container-tavo">
            <a href="{{ route('home') }}" data-reveal
               class="mb-[30px] flex w-fit items-center gap-2 text-[13px] font-semibold tracking-[.12em] text-muted uppercase">
                ← Zpět na úvod
            </a>
            {{-- Druhá část nadpisu se vysází cihlovou kurzívou. --}}
            <h1 data-reveal class="text-page-title m-0 max-w-[15ch] font-extrabold tracking-[-.03em]">
                {{ text('reference.nadpis', 'Na čem jsme', $skupina, 'Nadpis nad výpisem referencí') }}
                <span class="text-brick italic">{{ text('reference.nadpis_zvyrazneni', 'dělali.', $skupina, 'Zvýrazněná část nadpisu, vysází se cihlovou kurzívou') }}</span>
            </h1>
            <p data-reveal class="text-perex mt-[34px] mb-0 max-w-[52ch] text-body">
                {{ text('reference.perex', 'Výběr toho, co máme za sebou. Weby a značky staví Tom, reklamní účty vede Pavel. Čísla u nich neuvádíme, protože je nemáme od klientů ověřená.', $skupina, 'Text pod nadpisem na /reference') }}
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
                <p class="text-body-lg text-muted">{{ text('reference.prazdno', 'V této kategorii zatím žádný projekt nemáme.', $skupina, 'Hláška, když ve vybrané kategorii není žádná reference') }}</p>
            @else
                <div class="grid grid-cols-1 gap-[clamp(28px,3vw,48px)] menu:grid-cols-2">
                    @foreach ($cases as $case)
                        <a href="{{ route('cases.show', $case->slug) }}" data-reveal class="group block">
                            <div class="relative">
                                {{-- Rámeček 16:10. Náhledy chodí ve dvou tvarech — připravené koláže
                                     4:3 a screenshoty webů skoro 16:9 — a tenhle poměr leží mezi nimi,
                                     takže ani jednomu neubere víc než pár procent. --}}
                                <x-media
                                    :image="$case->thumbImage()"
                                    :label="$case->thumb_label"
                                    :priority="$loop->first"
                                    ratio="aspect-[16/10]"
                                    radius="rounded-thumb"
                                    sizes="(min-width: 861px) 44vw, 88vw"
                                    class="transition-transform duration-500 ease-tavo group-hover:scale-[1.02]" />

                                @if ($case->category)
                                    <span class="absolute top-4 left-4 rounded-pill bg-ink px-3 py-1.5 text-[11px] font-bold tracking-[.1em] text-cream uppercase">
                                        {{ $case->category->name }}
                                    </span>
                                @endif
                            </div>

                            <div class="mt-5">
                                {{-- Číslo drží řádek s nadpisem, popis pak jde přes celou šířku karty. --}}
                                <div class="flex items-start justify-between gap-4">
                                    <h2 class="text-h3-sm m-0 font-extrabold tracking-[-.02em] text-ink">{{ $case->title }}</h2>
                                    @if ($case->headline_metric)
                                        <span class="text-metric-sm font-extrabold tracking-[-.03em] whitespace-nowrap text-brick">
                                            {{ $case->headline_metric }}
                                        </span>
                                    @endif
                                </div>

                                <p class="mt-2 mb-0 text-[15px] leading-[1.5] text-muted">{{ $case->excerpt }}</p>

                                @if ($case->tags)
                                    <div class="mt-4 flex flex-wrap gap-2">
                                        @foreach ($case->tags as $tag)
                                            <x-tag size="xs">{{ $tag }}</x-tag>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <x-cta-band
        :title="text('reference.cta_nadpis', 'Řekněte nám, co potřebujete.', $skupina, 'Nadpis cihlového pruhu na konci /reference')"
        secondary-label="Co děláme"
        :secondary-url="route('home').'#sluzby'" />
</x-layout.app>
