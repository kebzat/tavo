@props(['home', 'portraits' => collect()])
{{-- Horní padding drží obsah pod fixní lištou, spodní ho vyvažuje, aby byl
     na mobilu opticky uprostřed obrazovky. --}}
<header id="top" class="section-x relative flex min-h-screen flex-col justify-center overflow-hidden pt-[110px] pb-[90px] text-center menu:pt-[100px] menu:pb-[60px]">
    <div class="container-tavo relative z-2 flex flex-col items-center">

        {{-- Pavel a Tom v kroužcích, druhý lehce přes prvního. Fotka se ořízne
             na čtverec kolem středu, s mírným posunem nahoru, ať u vyšší fotky
             nepřijdeme o temeno hlavy. Krémový lem kroužky od sebe odděluje.
             Kroužky se stejně jako nadpis zmenšují i podle výšky okna, ať se
             celý úvod včetně tlačítek vejde na obrazovku i na notebooku. --}}
        @if ($portraits->isNotEmpty())
            <div data-reveal class="mb-[clamp(20px,3.5vh,44px)] flex justify-center">
                @foreach ($portraits as $portrait)
                    <img src="{{ $portrait['src'] }}"
                         @if ($portrait['srcset']) srcset="{{ $portrait['srcset'] }}" sizes="136px" @endif
                         alt="{{ $portrait['alt'] }}"
                         width="136" height="136"
                         decoding="async" fetchpriority="high"
                         class="relative size-[clamp(72px,min(9.5vw,12vh),136px)] rounded-full bg-sand-300 object-cover object-[50%_30%] ring-4 ring-cream {{ $loop->first ? '' : '-ml-[clamp(12px,1.4vw,20px)]' }}">
                @endforeach
            </div>
        @endif

        {{-- Na mobilu bez čárky: vycentrovaný text by s ní vypadal posunutý doprava. --}}
        <x-eyebrow data-reveal rule="desktop" class="mb-[30px]">{{ $home->hero_eyebrow }}</x-eyebrow>

        <h1 class="text-hero m-0 max-w-[16ch] font-extrabold tracking-[-.03em]">
            <span data-line><span>{{ $home->hero_line_1 }}</span></span>
            <span data-line><span>{{ $home->hero_line_2 }}</span></span>
            <span data-line><span>{{ $home->hero_line_3 }}
                <span class="text-brick italic">{{ $home->hero_line_3_accent }}</span>
            </span></span>
        </h1>

        <div class="mt-[clamp(24px,4.5vh,44px)] flex flex-col items-center gap-[clamp(24px,4vh,36px)]">
            <p data-reveal class="text-perex m-0 max-w-[52ch] text-body">{{ $home->hero_perex }}</p>

            <div data-reveal class="flex flex-wrap justify-center gap-3.5">
                <x-btn :href="$home->hero_cta_primary_url" variant="primary">{{ $home->hero_cta_primary_label }}</x-btn>
                <x-btn :href="$home->hero_cta_secondary_url" variant="ghost">{{ $home->hero_cta_secondary_label }}</x-btn>
            </div>
        </div>
    </div>
</header>
