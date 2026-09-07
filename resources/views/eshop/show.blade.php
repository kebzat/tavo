{{--
    Dopadová stránka s nabídkou pro e-shopy. Obsah i strukturovaná data drží
    App\Support\EshopOffers, šablona je pro všechny čtyři stránky společná.

    Odpovědi v „Častých otázkách" jsou schválně rozbalené natvrdo, bez
    rozklikávání: totéž znění jde do JSON-LD FAQPage a Google odpovědi,
    které na stránce nejsou vidět, ignoruje.
--}}
<x-layout.app
    :title="$offer['seo_title']"
    :description="$offer['seo_description']"
    :schema="$schema">

    <header class="section-x pt-[150px] pb-[50px]">
        <div class="container-tavo">
            <a href="{{ route('home') }}" data-reveal
               class="mb-[30px] flex w-fit items-center gap-2 text-[13px] font-semibold tracking-[.12em] text-muted uppercase">
                ← Zpět na úvod
            </a>

            <x-eyebrow data-reveal :rule="true" class="mb-6">Pro e-shopy</x-eyebrow>

            <h1 data-reveal class="text-page-title m-0 max-w-[20ch] font-extrabold tracking-[-.03em]">
                {{ $offer['headline'] }}
            </h1>

            @foreach ($offer['intro'] as $paragraph)
                <p data-reveal class="text-perex mt-[34px] mb-0 max-w-[62ch] text-body">{{ $paragraph }}</p>
            @endforeach
        </div>
    </header>

    <section class="section-x section-y bg-cream">
        <div class="container-tavo">
            @foreach ($offer['sections'] as $section)
                <div data-reveal
                     class="grid grid-cols-1 items-start gap-[clamp(20px,4vw,90px)] border-t border-ink/14 py-[clamp(34px,4vw,60px)] menu:grid-cols-[0.85fr_1.15fr] @if ($loop->last) border-b @endif">
                    <h2 class="text-h2-sm m-0 font-extrabold tracking-[-.02em]">{{ $section['title'] }}</h2>

                    <div class="flex flex-col gap-5">
                        @foreach ($section['paragraphs'] as $paragraph)
                            <p class="text-body-lg m-0 max-w-[68ch] text-body">{{ $paragraph }}</p>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="section-x section-y-sm bg-ink text-cream">
        <div class="container-tavo grid grid-cols-1 items-start gap-[clamp(30px,5vw,90px)] menu:grid-cols-[0.85fr_1.15fr]">
            <h2 data-reveal class="text-h2 m-0 font-extrabold tracking-[-.02em]">Časté otázky</h2>

            <div data-reveal class="flex flex-col">
                @foreach ($offer['faq'] as $item)
                    <div class="border-t border-cream/15 py-[26px] @if ($loop->last) border-b @endif">
                        <h3 class="text-step m-0 font-extrabold tracking-[-.01em]">{{ $item['question'] }}</h3>
                        <p class="mt-3 mb-0 max-w-[62ch] text-[15px] leading-[1.6] text-cream/70">{{ $item['answer'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section-x section-y bg-cream">
        <div class="container-tavo">
            <h2 data-reveal class="text-h2 mt-0 mb-[46px] font-extrabold tracking-[-.02em]">Další nabídky pro e-shopy</h2>

            <div class="grid grid-cols-1 gap-6 menu:grid-cols-3">
                @foreach ($others as $other)
                    <a href="{{ $other['url'] }}" data-reveal
                       class="rounded-card flex flex-col border border-ink/12 bg-cream p-8 transition-transform duration-500 ease-tavo hover:-translate-y-1.5">
                        <div class="mb-3 text-xl font-extrabold tracking-[-.01em]">{{ $other['nav_label'] }}</div>
                        <p class="m-0 text-[15px] leading-[1.55] text-muted">{{ $other['headline'] }}</p>
                        <span class="mt-6 inline-flex items-center gap-1.5 text-[15px] font-bold text-brick">
                            Zjistit více
                            <span aria-hidden="true">→</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Poptávkový formulář je jeden, na úvodní stránce pod kotvou #kontakt. --}}
    <x-cta-band
        :title="$offer['cta_title']"
        :perex="$offer['cta_perex']"
        secondary-label="Poptat projekt"
        :secondary-url="route('home').'#kontakt'" />
</x-layout.app>
