{{--
    Sdílený audit klienta. Tmavá hlavička jako u checklistu, pod ní dlaždice
    s hlavními čísly a samotný text s obsahem v bočním sloupci. Text přichází
    z Markdownu už převedený do HTML, viz App\Support\AuditMarkdown.
--}}
<x-layout.document :title="$audit->title" :eyebrow="$audit->client?->name">

    <section class="section-x pt-6 pb-2">
        <div class="container-tavo">
            <div class="rounded-card bg-ink px-[7vw] py-11 text-cream menu:px-14 menu:py-14">
                <div class="flex flex-col gap-8 menu:flex-row menu:items-end menu:justify-between">
                    <div class="max-w-[60ch]">
                        @if ($audit->client)
                            <p class="mb-4 text-sm font-bold tracking-[.14em] text-brick uppercase">
                                {{ $audit->client->name }}
                            </p>
                        @endif

                        <h1 data-line class="text-h2-sm font-extrabold tracking-[-.02em]">
                            {{ $audit->title }}
                        </h1>

                        @if ($audit->intro)
                            <p class="mt-5 text-perex text-cream/70">{{ $audit->intro }}</p>
                        @endif

                        <x-client-docs.links :links="$links" class="mt-7" />
                    </div>

                    @if ($audit->audited_at)
                        <div class="shrink-0">
                            <p class="text-sm font-semibold text-cream/60">stav k</p>
                            <p class="mt-1 text-metric-sm font-extrabold tracking-[-.02em] text-brick tabular-nums">
                                {{ $audit->audited_at->format('j. n. Y') }}
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    @if ($tiles)
        <section class="section-x pt-5">
            <div class="container-tavo grid grid-cols-2 gap-3 menu:grid-cols-4 menu:gap-5">
                @foreach ($tiles as $tile)
                    <div class="rounded-card border border-ink/14 p-5 menu:p-7">
                        <p class="text-metric font-extrabold tracking-[-.03em] text-brick tabular-nums">{{ $tile['value'] }}</p>
                        @if ($tile['label'])
                            <p class="mt-2 text-sm leading-snug text-muted">{{ $tile['label'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="section-x section-y-sm">
        <div @class([
            'container-tavo grid gap-10 loop:gap-16',
            'loop:grid-cols-[230px_minmax(0,1fr)]' => $toc,
        ])>

            @if ($toc)
                {{-- Obsah: na širokém displeji přilepený sloupec, jinak rozbalovací seznam.
                     V bočním sloupci se zvýrazní kapitola, ve které čtenář právě je. --}}
                <nav aria-label="Obsah auditu"
                     x-data="tavoAuditToc"
                     class="loop:sticky loop:top-6 loop:max-h-[calc(100vh-48px)] loop:self-start loop:overflow-y-auto">
                    <details class="group rounded-card border border-ink/14 p-5 loop:hidden">
                        <summary class="flex cursor-pointer list-none items-center justify-between text-sm font-bold text-ink">
                            Obsah auditu
                            <span aria-hidden="true" class="text-muted transition-transform duration-300 ease-tavo group-open:rotate-45">+</span>
                        </summary>
                        <ol class="mt-4 flex flex-col gap-2">
                            @foreach ($toc as $entry)
                                <li>
                                    <a href="#{{ $entry['id'] }}" class="text-sm text-body transition-colors duration-200 ease-tavo hover:text-brick">
                                        {{ $entry['title'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ol>
                    </details>

                    <div class="hidden loop:block">
                        <p class="mb-4 text-xs font-bold tracking-[.14em] text-muted uppercase">Obsah</p>
                        <ol class="flex flex-col gap-2.5 border-l border-ink/12">
                            @foreach ($toc as $entry)
                                <li>
                                    <a href="#{{ $entry['id'] }}"
                                       :aria-current="active === @js($entry['id']) ? 'location' : false"
                                       class="-ml-px block border-l-2 border-transparent pl-4 text-sm leading-snug text-body transition-colors duration-200 ease-tavo hover:border-brick hover:text-brick aria-[current=location]:border-brick aria-[current=location]:font-bold aria-[current=location]:text-brick">
                                        {{ $entry['title'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                </nav>
            @endif

            <article data-audit-body class="prose-audit min-w-0">
                {!! $html !!}
            </article>
        </div>
    </section>
</x-layout.document>
