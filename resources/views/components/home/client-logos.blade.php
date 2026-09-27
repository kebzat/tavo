@props(['logos'])

{{-- Pás log „S kým spolupracujeme": klienti, kterým jsme dělali weby,
     e-shopy nebo kampaně. Loga zůstávají v barvách značek; ve stupních šedi
     by se některá ztratila. Na mobilu tři loga na řádek, ať pás není nekonečný;
     neúplná poslední řada se vycentruje. --}}
<section id="klienti" class="section-x bg-cream pb-[clamp(80px,11vw,150px)]">
    <div class="container-tavo border-t border-ink/14 pt-[clamp(40px,5vw,64px)]">
        <div class="mb-[clamp(28px,3.4vw,44px)] flex flex-wrap items-end justify-between gap-x-10 gap-y-4">
            <h2 data-reveal class="text-h3-sm m-0 max-w-[22ch] font-extrabold tracking-[-.01em]">
                {{ text('home.spoluprace_nadpis', 'S kým spolupracujeme', 'Homepage', 'Nadpis nad pásem log klientů') }}
            </h2>
            <p data-reveal class="m-0 max-w-[46ch] text-[15px] leading-[1.55] text-muted">
                {{ text('home.spoluprace_popis', 'Firmy a značky, kterým jsme stavěli weby a e-shopy nebo pro ně děláme kampaně.', 'Homepage', 'Text vedle nadpisu nad logy klientů') }}
            </p>
        </div>

        <ul data-reveal class="m-0 flex list-none flex-wrap justify-center gap-y-6 p-0 menu:gap-y-10">
            @foreach ($logos as $logo)
                <li class="flex h-12 basis-1/3 items-center justify-center px-3 min-[560px]:basis-1/4 menu:h-14 menu:basis-1/6 menu:px-6">
                    <img src="{{ $logo['src'] }}"
                         alt="{{ $logo['alt'] }}"
                         @if ($logo['width']) width="{{ $logo['width'] }}" height="{{ $logo['height'] }}" @endif
                         loading="lazy" decoding="async"
                         class="h-auto max-h-8 w-auto max-w-full object-contain menu:max-h-11 menu:max-w-[150px]">
                </li>
            @endforeach
        </ul>
    </div>
</section>
