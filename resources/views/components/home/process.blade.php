@props(['home', 'steps'])

<section id="proces" class="section-x bg-cream pb-[clamp(80px,11vw,150px)]">
    <div class="container-tavo border-t border-ink/14 pt-[clamp(50px,6vw,80px)]">
        <h2 data-reveal class="text-h2 mt-0 mb-[46px] font-extrabold tracking-[-.02em]">{{ $home->process_title }}</h2>

        <div class="grid grid-cols-2 gap-5 menu:grid-cols-5">
            @foreach ($steps as $step)
                <div data-reveal class="border-t-2 pt-[22px] {{ $step->highlight ? 'border-brick' : 'border-ink' }}">
                    <div class="mb-3.5 text-[13px] font-bold text-brick">{{ $step->number }}</div>
                    <div class="text-step mb-2.5 font-extrabold tracking-[-.01em]">{{ $step->title }}</div>
                    <div class="text-sm leading-[1.5] text-muted">{{ $step->text }}</div>
                </div>
            @endforeach
        </div>
    </div>
</section>
