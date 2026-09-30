@props([
    'example',
])

{{--
    Ukázka mezi sekcemi nabídky spolupráce: tmavý pruh s textem vlevo
    a obrázkem vpravo. Dlouhý screenshot (celý návrh homepage) sedí v okně
    prohlížeče, ve kterém se dá scrollovat, jinak by zabral deset obrazovek.
--}}
<section class="section-x section-y-sm bg-ink text-cream" data-block-bg="ink">
    <div @class([
        'container-tavo grid items-center gap-10 loop:gap-16',
        'menu:grid-cols-[0.8fr_1.2fr]' => $example['image'],
    ])>
        <div data-reveal class="max-w-[52ch]">
            @if ($example['kind'])
                <x-eyebrow class="mb-5">{{ $example['kind'] }}</x-eyebrow>
            @endif

            <h2 class="text-h2-sm font-extrabold tracking-[-.02em]">{{ $example['title'] }}</h2>

            @if ($example['body'])
                <p class="mt-5 whitespace-pre-line text-perex text-cream/70">{{ $example['body'] }}</p>
            @endif

            <div class="mt-8 flex flex-wrap gap-3">
                @if ($example['link_url'])
                    <a href="{{ $example['link_url'] }}" target="_blank" rel="noopener"
                       class="group inline-flex items-center gap-2 rounded-pill bg-cream px-5 py-3 text-sm font-bold text-ink transition duration-300 ease-tavo hover:-translate-y-0.5 hover:bg-brick hover:text-cream">
                        {{ $example['link_label'] }}
                        <span aria-hidden="true" class="transition-transform duration-300 ease-tavo group-hover:translate-x-0.5">↗</span>
                    </a>
                @endif

                @if ($example['image'] && $example['scroll'])
                    <a href="{{ $example['full_url'] }}" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-2 rounded-pill border-[1.5px] border-cream/30 px-5 py-3 text-sm font-bold text-cream transition duration-300 ease-tavo hover:-translate-y-0.5 hover:border-cream">
                        {{ text('spoluprace.open_full', 'Otevřít celý návrh') }}
                        <span aria-hidden="true">↗</span>
                    </a>
                @endif
            </div>
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
                    <x-media :image="$example['image']" fit="natural" tone="dark"
                             sizes="(min-width: 861px) 55vw, 88vw" />
                @endif
            </div>
        @endif
    </div>
</section>
