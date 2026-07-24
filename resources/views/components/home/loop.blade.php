@props(['home', 'items'])

<section class="section-x section-y overflow-hidden bg-ink text-cream">
    <div class="container-tavo">
        <h2 data-reveal class="text-h2-lg mt-0 mb-3 max-w-[18ch] font-extrabold tracking-[-.02em]">{{ $home->loop_title }}</h2>
        <p data-reveal class="text-body-lg mt-0 mb-10 max-w-[52ch] text-cream/65 menu:mb-14">{{ $home->loop_perex }}</p>

        {{-- Na mobilu jsou položky plochý seznam s vlasovými linkami zarovnaný
             k nadpisu, od `menu:` se z nich stávají karty v mřížce. --}}
        <div data-reveal class="grid grid-cols-1 gap-px overflow-hidden bg-cream/15 menu:gap-0.5 menu:rounded-[18px] loop:grid-cols-4 menu:grid-cols-2">
            @foreach ($items as $item)
                <div class="bg-ink py-6 menu:px-[26px] menu:py-[34px]">
                    <div class="mb-2.5 text-[13px] font-bold text-brick menu:mb-4">{{ $item['label'] }}</div>
                    <div class="mb-2 text-xl font-extrabold tracking-[-.01em] menu:mb-2.5">{{ $item['title'] }}</div>
                    <div class="text-sm leading-[1.55] text-cream/65">{{ $item['text'] }}</div>
                </div>
            @endforeach
        </div>
    </div>
</section>
