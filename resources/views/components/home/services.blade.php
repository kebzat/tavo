@props(['home', 'services'])

<section id="sluzby" class="section-x section-y bg-ink text-cream">
    <div class="container-tavo">
        <div class="mb-5 flex flex-wrap items-end justify-between gap-[30px]">
            <h2 data-reveal class="text-h2 m-0 font-extrabold tracking-[-.02em]">{{ $home->services_title }}</h2>
            <p data-reveal class="m-0 max-w-[34ch] text-[15px] text-cream/60">{{ $home->services_perex }}</p>
        </div>

        <div>
            @foreach ($services as $service)
                <div data-svc
                     class="grid grid-cols-[40px_1fr_auto] items-center gap-[26px] border-t border-cream/18 py-[clamp(28px,3.4vw,44px)] menu:grid-cols-[80px_1fr_auto] @if ($loop->last) border-b @endif">
                    <span class="text-sm font-bold text-brick">{{ $service->number }}</span>

                    <div>
                        <div class="text-svc font-extrabold tracking-[-.02em]">{{ $service->title }}</div>
                        <div class="mt-3.5 max-w-[60ch] text-[15px] leading-[1.5] text-cream/70">
                            {{ $service->excerpt }}
                            @if ($service->url())
                                <a href="{{ $service->url() }}" class="font-bold whitespace-nowrap text-brick">Zjistit více →</a>
                            @endif
                        </div>
                    </div>

                    <span data-svc-plus class="text-[34px] font-light text-brick">+</span>
                </div>
            @endforeach
        </div>
    </div>
</section>
