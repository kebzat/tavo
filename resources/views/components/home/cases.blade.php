@props(['home', 'cases'])

@if ($cases->isNotEmpty())
    <section id="reference" class="section-x section-y bg-cream">
        <div class="container-tavo">
            <div class="mb-14 flex flex-wrap items-end justify-between gap-[30px]">
                <h2 data-reveal class="text-h2 m-0 font-extrabold tracking-[-.02em]">{{ $home->cases_title }}</h2>
                <a data-reveal href="{{ route('cases.index') }}"
                   class="border-b-2 border-brick pb-[3px] text-[15px] font-bold text-ink">
                    {{ $home->cases_link_label }}
                </a>
            </div>

            @foreach ($cases as $case)
                <a href="{{ route('cases.show', $case->slug) }}"
                   data-reveal
                   class="mb-[90px] grid grid-cols-1 items-center gap-[clamp(28px,4vw,70px)] text-inherit no-underline last:mb-0 menu:grid-cols-[0.82fr_1.28fr]">
                    {{-- Pořadí drží původní design: vizuál vždy vlevo, text vpravo. --}}
                    <x-media
                        :image="$case->thumbImage()"
                        :label="$case->thumb_label"
                        :parallax="true"
                        sizes="(min-width: 861px) 34vw, 88vw" />

                    <div>
                        <div class="mb-4 text-[13px] font-bold tracking-[.14em] text-brick uppercase">{{ $case->eyebrow }}</div>
                        <h3 class="text-h3 mt-0 mb-[18px] font-extrabold tracking-[-.02em]">{{ $case->title }}</h3>
                        <p class="mt-0 mb-[26px] max-w-[48ch] text-base leading-[1.6] text-body">{{ $case->excerpt }}</p>

                        @if ($case->results)
                            <div class="flex flex-wrap gap-[34px]">
                                @foreach (array_slice($case->results, 0, 2) as $result)
                                    <div>
                                        <div class="text-metric font-extrabold tracking-[-.03em] text-ink">{{ $result['value'] }}</div>
                                        <div class="mt-0.5 text-[13px] text-muted">{{ $result['label'] }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if ($case->tags)
                            <div class="mt-[26px] flex flex-wrap gap-2.5">
                                @foreach ($case->tags as $tag)
                                    <x-tag>{{ $tag }}</x-tag>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    </section>
@endif
