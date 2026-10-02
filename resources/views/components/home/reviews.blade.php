@props(['home', 'testimonials'])

{{-- Recenze klientů, kotva #recenze (proklik z „5,0 na Googlu" v pruhu
     s čísly). Navazuje na pás log, proto stejné pozadí a linka nahoře.
     Prvních šest je vidět hned, zbytek po kliknutí. Mřížka jde po řádcích,
     ať se recenze Pavla a Toma střídají i na pohled. --}}
<section id="recenze" class="section-x bg-cream pb-[clamp(80px,11vw,150px)]"
         x-data="{ all: false }">
    <div class="container-tavo border-t border-ink/14 pt-[clamp(40px,5vw,64px)]">
        <div class="mb-[clamp(28px,3.4vw,44px)] flex flex-wrap items-end justify-between gap-x-10 gap-y-5">
            <div>
                @if ($home->reviews_title)
                    <h2 data-reveal class="text-h2 m-0 max-w-[18ch] font-extrabold tracking-[-.02em]">{{ $home->reviews_title }}</h2>
                @endif
                @if ($home->reviews_perex)
                    <p data-reveal class="mt-4 mb-0 max-w-[56ch] text-[15px] leading-[1.55] text-muted">{{ $home->reviews_perex }}</p>
                @endif
            </div>

            @if ($home->reviews_google_url)
                <x-btn data-reveal :href="$home->reviews_google_url" variant="ghost" target="_blank" rel="noopener" class="shrink-0">
                    {{ text('home.recenze_google', 'Hodnocení na Googlu', 'Homepage', 'Tlačítko u recenzí, vede na Google profil') }} ↗
                </x-btn>
            @endif
        </div>

        <div class="grid grid-cols-1 gap-5 menu:grid-cols-2 loop:grid-cols-3">
            @foreach ($testimonials as $testimonial)
                {{-- Skryté recenze nemají data-reveal: animace by je nechala
                     průhledné, protože při načtení stránky nebyly vidět. --}}
                <figure @if ($loop->index < 6) data-reveal @else x-cloak x-show="all" @endif
                        class="m-0 flex flex-col rounded-card border border-ink/12 bg-cream p-[clamp(22px,2.4vw,30px)]">
                    <blockquote class="m-0 mb-5 text-[15px] leading-[1.6] text-body">
                        „{{ $testimonial->text }}“
                    </blockquote>

                    <figcaption class="mt-auto flex items-end justify-between gap-4 border-t border-ink/10 pt-4">
                        <div class="min-w-0">
                            @if ($testimonial->source_url)
                                <a href="{{ $testimonial->source_url }}" target="_blank" rel="noopener nofollow"
                                   class="text-[15px] font-extrabold text-ink no-underline decoration-brick decoration-2 underline-offset-4 hover:underline">
                                    {{ $testimonial->author }}
                                </a>
                            @else
                                <span class="text-[15px] font-extrabold text-ink">{{ $testimonial->author }}</span>
                            @endif

                            @if ($testimonial->role)
                                <span class="mt-0.5 block text-[13px] leading-[1.4] text-muted">{{ $testimonial->role }}</span>
                            @endif
                        </div>

                        @if ($testimonial->personName())
                            <x-tag :tone="$testimonial->person === 'tom' ? 'brick' : 'light'" size="xs" class="shrink-0"
                                   title="{{ text('home.recenze_pro', 'Recenze pro', 'Homepage', 'Popisek štítku u recenze (pro hlasové čtečky)') }} {{ $testimonial->personName() }}">
                                {{ $testimonial->personName() }}
                            </x-tag>
                        @endif
                    </figcaption>
                </figure>
            @endforeach
        </div>

        @if ($testimonials->count() > 6)
            <div class="mt-8 flex justify-center" x-show="! all">
                <x-btn type="button" variant="ghost" x-on:click="all = true">
                    {{ text('home.recenze_dalsi', 'Zobrazit další recenze', 'Homepage', 'Tlačítko pod recenzemi') }} ({{ $testimonials->count() - 6 }})
                </x-btn>
            </div>
        @endif
    </div>
</section>
