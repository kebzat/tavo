@props(['home'])

<section id="proc" class="section-x section-y bg-ink text-cream">
    <div class="container-tavo">
        <div class="grid grid-cols-1 items-start gap-[clamp(40px,6vw,110px)] menu:grid-cols-2">
            <div>
                <x-eyebrow data-reveal class="mb-[22px]">{{ $home->problem_eyebrow }}</x-eyebrow>
                <h2 data-reveal class="text-h2-lg m-0 font-extrabold tracking-[-.02em]">
                    {!! nl2br(e($home->problem_title)) !!}
                </h2>
            </div>

            <div class="pt-2.5">
                <p data-reveal class="text-body-lg mt-0 mb-[30px] text-cream/78">{{ $home->problem_perex }}</p>

                <div data-reveal class="flex flex-col">
                    @foreach ($home->problem_points as $i => $point)
                        <div class="flex items-baseline gap-[18px] border-t border-cream/15 py-4">
                            <span class="text-sm font-bold text-brick">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="text-base leading-[1.4]">{{ $point }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
