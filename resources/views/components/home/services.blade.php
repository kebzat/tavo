@props(['home', 'services'])

<section id="sluzby" class="section-x section-y bg-ink text-cream">
    <div class="container-tavo">
        <div class="mb-5 flex flex-wrap items-end justify-between gap-[30px]">
            <h2 data-reveal class="text-h2 m-0 font-extrabold tracking-[-.02em]">{{ $home->services_title }}</h2>
            <p data-reveal class="m-0 max-w-[34ch] text-[15px] text-cream/60">{{ $home->services_perex }}</p>
        </div>

        <div>
            {{-- Na mobilu je číslo s plusem v prvním řádku a text pod nimi přes celou
                 šířku, od `menu:` se vrací původní tři sloupce vedle sebe. --}}
            @foreach ($services as $service)
                <div data-svc @if ($service->url()) data-svc-link @endif
                     class="relative grid grid-cols-[1fr_auto] items-center gap-x-4 gap-y-3 border-t border-cream/18 py-[26px] menu:grid-cols-[80px_1fr_auto] menu:gap-[26px] menu:py-[clamp(28px,3.4vw,44px)] @if ($loop->last) border-b @endif">
                    <span class="text-sm font-bold text-brick menu:col-start-1 menu:row-start-1">{{ $service->number }}</span>

                    <span data-svc-plus class="justify-self-end text-[30px] leading-none font-light text-brick menu:col-start-3 menu:row-start-1 menu:text-[34px]">+</span>

                    <div class="col-span-2 menu:col-span-1 menu:col-start-2 menu:row-start-1">
                        <div class="text-svc font-extrabold tracking-[-.02em]">{{ $service->title }}</div>
                        <div class="mt-3 max-w-[60ch] text-[15px] leading-[1.55] text-cream/70 menu:mt-3.5">
                            {{ $service->excerpt }}
                        </div>
                        @if ($service->url())
                            <span class="mt-4 inline-flex items-center gap-1.5 text-[15px] font-bold text-brick">
                                Zjistit více
                                <span data-svc-arrow class="transition-transform duration-300 ease-tavo">→</span>
                            </span>
                        @endif
                    </div>

                    @if ($service->url())
                        <a href="{{ $service->url() }}"
                           class="absolute inset-0 rounded-[2px] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brick"
                           aria-label="{{ $service->title }} – zjistit více"></a>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
