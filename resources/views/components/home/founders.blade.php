@props(['home', 'founders', 'photo' => null])

<section id="lide" class="section-x section-y bg-cream">
    <div class="container-tavo">
        <div class="mb-[52px] flex flex-wrap items-end justify-between gap-[30px]">
            <h2 data-reveal class="text-h2 m-0 max-w-[16ch] font-extrabold tracking-[-.02em]">{{ $home->founders_title }}</h2>
            <p data-reveal class="m-0 max-w-[34ch] text-[15px] text-muted">{{ $home->founders_perex }}</p>
        </div>

        <div class="grid grid-cols-1 items-center gap-[clamp(24px,4vw,64px)] menu:grid-cols-[1.05fr_0.95fr]">
            <div data-reveal class="relative overflow-hidden rounded-card bg-ink">
                @if ($photo)
                    <img src="{{ $photo['src'] }}"
                         @if ($photo['srcset']) srcset="{{ $photo['srcset'] }}" sizes="(min-width: 861px) 45vw, 88vw" @endif
                         alt="{{ $photo['alt'] }}"
                         @if ($photo['width']) width="{{ $photo['width'] }}" height="{{ $photo['height'] }}" @endif
                         loading="lazy" decoding="async"
                         class="block aspect-square h-full w-full object-cover">
                @else
                    <div class="aspect-square w-full hatch-dark"></div>
                @endif

                <div class="absolute bottom-5 left-[22px] flex gap-2">
                    @foreach ($founders as $founder)
                        <span class="rounded-pill px-[13px] py-[7px] text-xs font-bold tracking-[.06em] {{ $loop->first ? 'bg-cream/92 text-ink' : 'bg-brick/95 text-white' }}">
                            {{ $founder->name }}
                        </span>
                    @endforeach
                </div>
            </div>

            <div data-reveal>
                <p class="mt-0 mb-[30px] max-w-[42ch] text-[clamp(17px,1.8vw,23px)] leading-[1.5] font-medium tracking-[-.01em] text-ink">
                    {{ $home->founders_intro }}
                </p>

                <div>
                    @foreach ($founders as $founder)
                        {{-- Uzavírací linka jen od `menu:` — na mobilu je pod ní hned
                             oddělovač sekce „Jak to probíhá“ a dělaly by dvě čáry. --}}
                        <div class="border-t border-ink/14 py-[22px] @if ($loop->last) menu:border-b @endif">
                            <div class="mb-2.5 flex items-baseline gap-3">
                                <span class="text-[22px] font-extrabold tracking-[-.01em]">{{ $founder->name }}</span>
                                <span class="text-xs font-bold tracking-[.12em] text-brick uppercase">{{ $founder->role_label }}</span>
                            </div>
                            <p class="mt-0 mb-3 text-[15px] leading-[1.55] text-muted">{{ $founder->bio }}</p>

                            @if ($founder->tags)
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($founder->tags as $tag)
                                        <x-tag>{{ $tag }}</x-tag>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        @if ($home->founders_network_title || $home->founders_network_text)
            <div data-reveal class="mt-[clamp(40px,5vw,72px)] border-t border-ink/14 pt-[clamp(28px,3.4vw,44px)]">
                {{-- Stejné dělení sloupců jako hlavní mřížka sekce, ať nadpis sedí
                     pod fotkou a text pod medailonky. --}}
                <div class="grid grid-cols-1 gap-[clamp(18px,4vw,64px)] menu:grid-cols-[1.05fr_0.95fr]">
                    @if ($home->founders_network_title)
                        <h3 class="text-h3-sm m-0 max-w-[16ch] font-extrabold tracking-[-.01em]">{{ $home->founders_network_title }}</h3>
                    @endif

                    <div>
                        @if ($home->founders_network_text)
                            <p class="m-0 max-w-[62ch] text-[15px] leading-[1.6] text-muted">{{ $home->founders_network_text }}</p>
                        @endif

                        @if ($home->founders_network_items)
                            <div class="mt-5 flex flex-wrap gap-2">
                                @foreach ($home->founders_network_items as $item)
                                    <x-tag>{{ $item }}</x-tag>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>
</section>
