@props(['home', 'testimonials'])

{{-- Recenze klientů, kotva #recenze (proklik z „5,0 na Googlu" v pruhu
     s čísly). Navazuje na pás log, proto stejné pozadí a linka nahoře.
     Posuvník (tavoSlider v app.js): na počítači tři karty vedle sebe, na
     tabletu dvě, na mobilu jedna a kousek další, ať je vidět, že jde listovat.
     Recenze Pavla a Toma se v pořadí střídají. --}}
<section id="recenze" class="section-x bg-cream pb-[clamp(80px,11vw,150px)]"
         x-data="tavoSlider">
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

            <div data-reveal class="flex shrink-0 items-center gap-3">
                @if ($home->reviews_google_url)
                    <x-btn :href="$home->reviews_google_url" variant="ghost" target="_blank" rel="noopener">
                        {{ text('home.recenze_google', 'Hodnocení na Googlu', 'Homepage', 'Tlačítko u recenzí, vede na Google profil') }} ↗
                    </x-btn>
                @endif

                {{-- Šipky jen tehdy, když se všechny recenze nevejdou vedle sebe. --}}
                <div class="flex gap-2" x-show="! (atStart && atEnd)">
                    <button type="button" x-on:click="move(-1)" x-bind:disabled="atStart"
                            aria-label="{{ text('home.recenze_predchozi', 'Předchozí recenze', 'Homepage', 'Šipka posuvníku recenzí (pro hlasové čtečky)') }}"
                            class="flex h-[54px] w-[54px] cursor-pointer items-center justify-center rounded-pill border-[1.5px] border-ink/28 text-xl text-ink transition duration-300 ease-tavo hover:border-ink hover:bg-ink hover:text-cream disabled:cursor-default disabled:opacity-30 disabled:hover:border-ink/28 disabled:hover:bg-transparent disabled:hover:text-ink">
                        <span aria-hidden="true">←</span>
                    </button>
                    <button type="button" x-on:click="move(1)" x-bind:disabled="atEnd"
                            aria-label="{{ text('home.recenze_dalsi_sipka', 'Další recenze', 'Homepage', 'Šipka posuvníku recenzí (pro hlasové čtečky)') }}"
                            class="flex h-[54px] w-[54px] cursor-pointer items-center justify-center rounded-pill border-[1.5px] border-ink/28 text-xl text-ink transition duration-300 ease-tavo hover:border-ink hover:bg-ink hover:text-cream disabled:cursor-default disabled:opacity-30 disabled:hover:border-ink/28 disabled:hover:bg-transparent disabled:hover:text-ink">
                        <span aria-hidden="true">→</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- data-reveal je na celém pásu, ne na kartách: karty mimo obrazovku
             by animace nikdy neodkryla a zůstaly by průhledné. --}}
        <div data-reveal
             x-ref="track"
             tabindex="0"
             role="region"
             aria-label="{{ $home->reviews_title ?: text('home.recenze_popisek', 'Recenze klientů', 'Homepage', 'Popisek posuvníku recenzí (pro hlasové čtečky)') }}"
             class="flex snap-x snap-mandatory gap-5 overflow-x-auto overscroll-x-contain pb-1 outline-none [scrollbar-width:none] focus-visible:ring-2 focus-visible:ring-brick/40 [&::-webkit-scrollbar]:hidden">
            @foreach ($testimonials as $testimonial)
                <figure class="m-0 flex w-[86%] shrink-0 snap-start flex-col rounded-card border border-ink/12 bg-cream p-[clamp(22px,2.4vw,30px)] menu:w-[calc((100%-20px)/2)] loop:w-[calc((100%-40px)/3)]">
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

        {{-- Ukazatel pozice: tenká linka, cihlový úsek je viditelná část pásu. --}}
        <div x-show="! (atStart && atEnd)" aria-hidden="true" class="relative mt-8 h-[3px] overflow-hidden rounded-pill bg-ink/10">
            <div class="absolute inset-y-0 rounded-pill bg-brick transition-[left] duration-150"
                 x-bind:style="`width: ${visible * 100}%; left: ${progress * (1 - visible) * 100}%`"></div>
        </div>
    </div>
</section>
