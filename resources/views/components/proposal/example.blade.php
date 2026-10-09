@props([
    'example',
    'light' => false,   // světlý pruh, když by tmavý splynul s tmavou sekcí vedle
])

{{--
    Ukázka mezi sekcemi nabídky spolupráce: tmavý pruh s textem vlevo
    a obrázkem vpravo. Dlouhý screenshot (celý návrh homepage) sedí v okně
    prohlížeče, ve kterém se dá scrollovat, jinak by zabral deset obrazovek.

    S mřížkou ukázek (`items`) je text nahoře a pod ním screenshoty ve dvou
    sloupcích, nebo videa na výšku ve čtyřech. Screenshoty z mobilu (`phones`)
    stojí nad mřížkou vedle sebe v rámečku telefonu, ve kterém se dá scrollovat;
    na úzkém displeji se telefony posouvají do strany. Video z Google Disku se načte
    až po kliknutí, do té doby je tam jen náhled, ať stránka zůstane rychlá.
--}}
<section @class([
    'section-x section-y-sm',
    'bg-ink text-cream' => ! $light,
    'text-ink' => $light,
]) @if (! $light) data-block-bg="ink" @endif>
    <div @class([
        'container-tavo grid items-center gap-10 loop:gap-16',
        'menu:grid-cols-[0.8fr_1.2fr]' => $example['image'] && ! $example['items'],
    ])>
        <div data-reveal @class(['max-w-[52ch]' => ! $example['items'] && ! $example['phones'], 'max-w-[64ch]' => $example['items'] || $example['phones']])>
            @if ($example['kind'])
                <x-eyebrow class="mb-5">{{ $example['kind'] }}</x-eyebrow>
            @endif

            <h2 class="text-h2-sm font-extrabold tracking-[-.02em]">{{ $example['title'] }}</h2>

            @if ($example['body'])
                <p @class([
                    'mt-5 whitespace-pre-line text-perex',
                    'text-cream/70' => ! $light,
                    'text-body' => $light,
                ])>{{ $example['body'] }}</p>
            @endif

            {{-- U mřížky ukázek stojí odkaz až pod ní, viz konec komponenty. --}}
            @if (($example['link_url'] && ! $example['items'] && ! $example['phones']) || ($example['image'] && $example['scroll']))
                <div class="mt-8 flex flex-wrap gap-3">
                    @if ($example['link_url'] && ! $example['items'] && ! $example['phones'])
                        <x-proposal.example-link :example="$example" :light="$light" />
                    @endif

                    @if ($example['image'] && $example['scroll'])
                        <a href="{{ $example['full_url'] }}" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-2 rounded-pill border-[1.5px] border-cream/30 px-5 py-3 text-sm font-bold text-cream transition duration-300 ease-tavo hover:-translate-y-0.5 hover:border-cream">
                            {{ text('spoluprace.open_full', 'Otevřít celý návrh') }}
                            <span aria-hidden="true">↗</span>
                        </a>
                    @endif
                </div>
            @endif
        </div>

        @if ($example['image'])
            <div data-reveal>
                @if ($example['scroll'])
                    <div class="overflow-hidden rounded-card border border-cream/12 bg-ink-soft shadow-[0_30px_80px_-30px_rgba(0,0,0,.8)]">
                        <div aria-hidden="true" class="flex items-center gap-1.5 border-b border-cream/10 px-4 py-3">
                            <span class="size-2.5 rounded-full bg-cream/20"></span>
                            <span class="size-2.5 rounded-full bg-cream/20"></span>
                            <span class="size-2.5 rounded-full bg-cream/20"></span>
                        </div>
                        <div tabindex="0" aria-label="{{ $example['image']['alt'] ?: $example['title'] }}"
                             class="max-h-[min(72vh,720px)] overflow-y-auto overscroll-contain focus-visible:outline-2 focus-visible:outline-brick">
                            <x-media :image="$example['image']" fit="natural" radius="rounded-none"
                                     sizes="(min-width: 861px) 55vw, 88vw" />
                        </div>
                    </div>
                    <p class="mt-3 text-sm text-cream/50">{{ text('spoluprace.scroll_hint', 'V okně se dá scrollovat.') }}</p>
                @else
                    <x-media :image="$example['image']" fit="natural" :tone="$light ? 'light' : 'dark'"
                             sizes="(min-width: 861px) 55vw, 88vw" />
                @endif
            </div>
        @endif

        @if ($example['phones'])
            <div data-reveal class="min-w-0">
                <ul class="-mx-[6vw] flex snap-x snap-mandatory gap-6 overflow-x-auto px-[6vw] pb-4 menu:mx-0 menu:justify-center menu:gap-12 menu:overflow-visible menu:px-0">
                    @foreach ($example['phones'] as $phone)
                        <li class="flex w-[280px] shrink-0 snap-center flex-col menu:w-[320px]">
                            <div class="rounded-[46px] bg-[#0d0c0b] p-2.5 shadow-[0_30px_80px_-30px_rgba(0,0,0,.8)] ring-1 ring-cream/15">
                                <div class="overflow-hidden rounded-[37px] bg-white">
                                    <div aria-hidden="true" class="flex h-8 items-center justify-center">
                                        <span class="h-5 w-20 rounded-full bg-[#0d0c0b]"></span>
                                    </div>
                                    <div tabindex="0" aria-label="{{ $phone['image']['alt'] ?: $phone['title'] }}"
                                         class="h-[540px] overflow-y-auto overscroll-contain [scrollbar-width:none] focus-visible:outline-2 focus-visible:outline-brick menu:h-[620px]">
                                        <x-media :image="$phone['image']" fit="natural" radius="rounded-none" sizes="320px" />
                                    </div>
                                </div>
                            </div>
                            @if ($phone['title'])
                                <h3 class="mt-5 text-step font-extrabold tracking-[-.01em]">{{ $phone['title'] }}</h3>
                            @endif
                            @if ($phone['body'])
                                <p @class([
                                    'mt-2 whitespace-pre-line text-perex',
                                    'text-cream/60' => ! $light,
                                    'text-body' => $light,
                                ])>{{ $phone['body'] }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <p @class(['mt-3 text-center text-sm', 'text-cream/50' => ! $light, 'text-muted' => $light])>
                    {{ text('spoluprace.phone_hint', 'V telefonu se dá scrollovat jako na mobilu.') }}
                </p>
            </div>
        @endif

        @if ($example['items'])
            <ul @class([
                'grid',
                'grid-cols-2 gap-x-4 gap-y-8 menu:grid-cols-4 menu:gap-x-5' => $example['items_layout'] === 'video',
                'gap-12 menu:grid-cols-2 menu:gap-x-8 menu:gap-y-14' => $example['items_layout'] !== 'video',
            ])>
                @foreach ($example['items'] as $item)
                    <li data-reveal class="flex flex-col">
                        @if ($item['video'])
                            <div x-data="{ playing: false }"
                                 @class([
                                     'relative aspect-[9/16] overflow-hidden rounded-card',
                                     'bg-ink-soft' => ! $light,
                                     'bg-ink' => $light,
                                 ])>
                                <button type="button" x-show="! playing" x-on:click="playing = true"
                                        class="group absolute inset-0 block h-full w-full cursor-pointer"
                                        aria-label="{{ text('spoluprace.video_play', 'Přehrát video') }}: {{ $item['title'] }}">
                                    @if ($item['image'])
                                        <img src="{{ $item['image']['src'] }}"
                                             @if ($item['image']['srcset']) srcset="{{ $item['image']['srcset'] }}" sizes="(min-width: 861px) 22vw, 44vw" @endif
                                             alt="{{ $item['image']['alt'] }}" loading="lazy" decoding="async"
                                             class="absolute inset-0 h-full w-full object-cover">
                                    @else
                                        <img src="{{ $item['video']['poster_url'] }}" alt="" loading="lazy" decoding="async"
                                             referrerpolicy="no-referrer"
                                             class="absolute inset-0 h-full w-full object-cover">
                                    @endif
                                    <span aria-hidden="true"
                                          class="absolute inset-0 bg-gradient-to-t from-ink/60 via-transparent to-transparent"></span>
                                    <span aria-hidden="true"
                                          class="absolute left-1/2 top-1/2 flex size-16 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-cream text-ink shadow-[0_12px_30px_-10px_rgba(0,0,0,.6)] transition duration-300 ease-tavo group-hover:scale-110 group-hover:bg-brick group-hover:text-cream">
                                        <svg viewBox="0 0 24 24" class="ml-1 size-6" fill="currentColor"><path d="M7 4.5v15l13-7.5z"/></svg>
                                    </span>
                                </button>
                                <template x-if="playing">
                                    <iframe src="{{ $item['video']['embed_url'] }}" title="{{ $item['title'] }}"
                                            allow="autoplay; fullscreen" allowfullscreen
                                            class="absolute inset-0 h-full w-full border-0"></iframe>
                                </template>
                            </div>
                        @elseif ($item['image'] && $example['items_layout'] === 'video')
                            {{-- Banner na výšku mezi videi: stejný rámeček, obrázek celý. --}}
                            <a href="{{ $item['full_url'] }}" target="_blank" rel="noopener"
                               @class([
                                   'relative block aspect-[9/16] overflow-hidden rounded-card transition duration-300 ease-tavo hover:-translate-y-1',
                                   'bg-ink-soft' => ! $light,
                                   'bg-ink' => $light,
                               ])>
                                <img src="{{ $item['image']['src'] }}"
                                     @if ($item['image']['srcset']) srcset="{{ $item['image']['srcset'] }}" sizes="(min-width: 861px) 22vw, 44vw" @endif
                                     alt="{{ $item['image']['alt'] }}" loading="lazy" decoding="async"
                                     class="absolute inset-0 h-full w-full object-contain">
                            </a>
                        @elseif ($item['image'])
                            <a href="{{ $item['full_url'] }}" target="_blank" rel="noopener"
                               @class([
                                   'group block overflow-hidden rounded-card border bg-white transition duration-300 ease-tavo hover:-translate-y-1',
                                   'border-ink/14 shadow-[0_24px_60px_-34px_rgba(19,17,16,.45)]' => $light,
                                   'border-cream/12' => ! $light,
                               ])>
                                <x-media :image="$item['image']" fit="natural" radius="rounded-none"
                                         sizes="(min-width: 861px) 45vw, 88vw" />
                            </a>
                        @endif

                        @if ($item['title'])
                            <h3 @class([
                                'font-extrabold tracking-[-.01em]',
                                'mt-5 text-step' => $example['items_layout'] !== 'video',
                                'mt-3.5 text-base' => $example['items_layout'] === 'video',
                            ])>{{ $item['title'] }}</h3>
                        @endif
                        @if ($item['body'])
                            <p @class([
                                'whitespace-pre-line',
                                'mt-2 max-w-[56ch] text-perex' => $example['items_layout'] !== 'video',
                                'mt-1 text-sm leading-snug' => $example['items_layout'] === 'video',
                                'text-cream/60' => ! $light,
                                'text-body' => $light,
                            ])>{{ $item['body'] }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>

        @endif

        {{-- Odkaz na další ukázky (třeba /pro-klienty) až za těmi, které čtenář právě viděl. --}}
        @if ($example['link_url'] && ($example['items'] || $example['phones']))
            <div data-reveal class="flex justify-center">
                <x-proposal.example-link :example="$example" :light="$light" />
            </div>
        @endif
    </div>
</section>
