@props(['home', 'founders'])

<section id="lide" class="section-x section-y bg-cream">
    <div class="container-tavo">
        <div class="mb-[52px] flex flex-wrap items-end justify-between gap-[30px]">
            <h2 data-reveal class="text-h2 m-0 max-w-[16ch] font-extrabold tracking-[-.02em]">{{ $home->founders_title }}</h2>
            <p data-reveal class="m-0 max-w-[34ch] text-[15px] text-muted">{{ $home->founders_perex }}</p>
        </div>

        <div class="grid grid-cols-1 items-center gap-[clamp(24px,4vw,64px)] menu:grid-cols-[1.05fr_0.95fr]">
            @php($photo = $founders->map->photoUrl()->filter()->first())

            <div data-reveal class="relative overflow-hidden rounded-card bg-ink">
                @if ($photo)
                    <img src="{{ $photo }}" alt="Zakladatelé {{ $founders->pluck('name')->join(' a ') }}"
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
                        <div class="border-t border-ink/14 py-[22px] @if ($loop->last) border-b @endif">
                            <div class="mb-2.5 flex items-baseline gap-3">
                                <span class="text-[22px] font-extrabold tracking-[-.01em]">{{ $founder->name }}</span>
                                <span class="text-xs font-bold tracking-[.12em] text-brick uppercase">{{ $founder->role_label }}</span>
                            </div>
                            <p class="mt-0 mb-3 text-[15px] leading-[1.55] text-muted">{{ $founder->bio }}</p>

                            @if ($founder->tags)
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($founder->tags as $tag)
                                        <x-tag>{{ $tag['text'] }}</x-tag>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
