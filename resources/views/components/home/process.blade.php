@props(['home', 'steps'])

<section id="proces" class="section-x bg-cream pb-[clamp(80px,11vw,150px)]">
    <div class="container-tavo border-t border-ink/14 pt-[clamp(50px,6vw,80px)]">
        <h2 data-reveal class="text-h2 mt-0 mb-[46px] font-extrabold tracking-[-.02em]">{{ $home->process_title }}</h2>

        {{-- Na mobilu jdou kroky pod sebou, číslo je v jednom řádku s názvem;
             od `menu:` se vrací pět sloupců vedle sebe. --}}
        <div class="grid grid-cols-1 gap-0 menu:grid-cols-5 menu:gap-5">
            @foreach ($steps as $step)
                <div data-reveal class="border-t-2 pt-[18px] pb-7 menu:pt-[22px] menu:pb-0 {{ $step->highlight ? 'border-brick' : 'border-ink' }}">
                    <div class="flex items-baseline gap-3.5 menu:block">
                        <div class="text-[13px] font-bold text-brick menu:mb-3.5">{{ $step->number }}</div>
                        <div class="text-step font-extrabold tracking-[-.01em] menu:mb-2.5">{{ $step->title }}</div>
                    </div>
                    <div class="mt-2 text-sm leading-[1.55] text-muted menu:mt-0">{{ $step->text }}</div>
                </div>
            @endforeach
        </div>
    </div>
</section>
