@props(['home', 'plans'])

<section id="cenik" class="section-x section-y bg-ink text-cream">
    <div class="container-tavo">
        <div class="mb-[46px] flex flex-wrap items-end justify-between gap-[30px]">
            @if ($home->pricing_title)
                <h2 data-reveal class="text-h2 m-0 max-w-[16ch] font-extrabold tracking-[-.02em]">{{ $home->pricing_title }}</h2>
            @endif
            @if ($home->pricing_perex)
                <p data-reveal class="m-0 max-w-[44ch] text-[15px] leading-[1.55] text-cream/60">{{ $home->pricing_perex }}</p>
            @endif
        </div>

        {{-- Každá karta je subgrid přes čtyři řádky (hlavička, popis, cena, poznámka),
             takže ceny leží na jedné lince, i když má jen jedna karta poznámku
             nebo delší popis. Prázdné řádky zůstávají jako nulová výška. --}}
        <div class="grid grid-cols-1 gap-4 menu:gap-5 loop:grid-cols-3">
            @foreach ($plans as $plan)
                <div data-reveal class="row-span-4 grid grid-rows-subgrid gap-y-0 rounded-card border bg-ink-soft p-[clamp(26px,2.6vw,36px)] {{ $plan['highlight'] ? 'border-brick' : 'border-cream/12' }}">
                    <div>
                        <div class="text-h3-sm font-extrabold tracking-[-.01em]">{{ $plan['name'] }}</div>
                        @if ($plan['when'])
                            <p class="mt-3 mb-0 text-[15px] leading-[1.45] font-bold text-brick">{{ $plan['when'] }}</p>
                        @endif
                    </div>

                    <div>
                        @if ($plan['text'])
                            <p class="mt-4 mb-0 text-[15px] leading-[1.6] text-cream/70">{{ $plan['text'] }}</p>
                        @endif
                    </div>

                    <div class="mt-8 flex flex-wrap items-baseline gap-x-2 gap-y-1 border-t border-cream/15 pt-6">
                        <span class="text-metric-sm leading-none font-extrabold tracking-[-.02em] whitespace-nowrap">{{ $plan['price'] }}</span>
                        @if ($plan['price_unit'])
                            <span class="text-[15px] font-bold whitespace-nowrap text-cream/60">{{ $plan['price_unit'] }}</span>
                        @endif
                    </div>

                    <div>
                        @if ($plan['price_note'])
                            <p class="mt-3 mb-0 text-sm leading-[1.55] text-cream/60">{{ $plan['price_note'] }}</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Úvodní konzultace zdarma: pod cenami, aby ceník nikoho neodradil
             od prvního kroku. Tlačítko vede na formulář na konci stránky. --}}
        @if ($home->pricing_free_title)
            <div data-reveal class="mt-[clamp(28px,3vw,44px)] flex flex-col gap-5 border-t border-cream/15 pt-[clamp(28px,3vw,44px)] loop:flex-row loop:items-center loop:gap-10">
                <h3 class="text-h3-sm m-0 shrink-0 font-extrabold tracking-[-.01em]">{{ $home->pricing_free_title }}</h3>

                @if ($home->pricing_free_text)
                    <p class="m-0 max-w-[60ch] text-[17px] leading-[1.5] text-brick loop:flex-1">{{ $home->pricing_free_text }}</p>
                @endif

                @if ($home->pricing_free_cta_label)
                    <x-btn href="#kontakt" variant="primary" class="shrink-0 self-start loop:self-auto">{{ $home->pricing_free_cta_label }}</x-btn>
                @endif
            </div>
        @endif
    </div>
</section>
