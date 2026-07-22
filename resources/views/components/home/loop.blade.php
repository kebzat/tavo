@props(['home', 'items'])

<section class="section-x section-y overflow-hidden bg-ink text-cream">
    <div class="container-tavo">
        <h2 data-reveal class="text-h2-lg mt-0 mb-3 max-w-[18ch] font-extrabold tracking-[-.02em]">{{ $home->loop_title }}</h2>
        <p data-reveal class="text-body-lg mt-0 mb-14 max-w-[52ch] text-cream/65">{{ $home->loop_perex }}</p>

        <div class="grid grid-cols-1 gap-0.5 overflow-hidden rounded-[18px] bg-cream/15 loop:grid-cols-4 menu:grid-cols-2">
            @foreach ($items as $item)
                <div data-reveal class="bg-ink px-[26px] py-[34px]">
                    <div class="mb-4 text-[13px] font-bold text-brick">{{ $item['label'] }}</div>
                    <div class="mb-2.5 text-xl font-extrabold tracking-[-.01em]">{{ $item['title'] }}</div>
                    <div class="text-sm leading-[1.5] text-cream/65">{{ $item['text'] }}</div>
                </div>
            @endforeach
        </div>
    </div>
</section>
