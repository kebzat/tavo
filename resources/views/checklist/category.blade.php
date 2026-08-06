{{--
    Jedna kategorie checklistu. Sekce jsou podnadpisy, položka je jeden řádek
    s odškrtávacím políčkem. Vysvětlivka se rozbalí až kliknutím, ať tabulka
    zůstane přehledná.
--}}
<x-layout.document :title="$category->title.' — '.$checklist->name" :eyebrow="$checklist->client?->name">

    <x-checklist.hero :checklist="$checklist" :progress="$checklist->progress()" />

    <section class="section-x section-y-sm">
        <div class="container-tavo">

            {{-- Přepínač kategorií. Vodorovný pruh místo bočního sloupce,
                 na mobilu se odroluje do strany. --}}
            <nav aria-label="Kategorie checklistu" class="-mx-[6vw] mb-10 overflow-x-auto px-[6vw]">
                <ul class="flex w-max gap-2">
                    @foreach ($checklist->categories as $polozkaMenu)
                        <li>
                            <a href="{{ route('checklist.category', [$checklist->public_token, $polozkaMenu->slug]) }}"
                               @class([
                                   'block rounded-pill px-4 py-2 text-sm font-bold transition duration-300 ease-tavo',
                                   'bg-ink text-cream' => $polozkaMenu->is($category),
                                   'bg-ink/6 text-body hover:bg-ink/12' => ! $polozkaMenu->is($category),
                               ])
                               @if ($polozkaMenu->is($category)) aria-current="page" @endif>
                                {{ $polozkaMenu->title }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <header class="mb-8 flex flex-col gap-6 menu:flex-row menu:items-end menu:justify-between">
                <div class="max-w-[52ch]">
                    <h2 class="text-h3 font-extrabold tracking-[-.02em] text-ink">{{ $category->title }}</h2>

                    @if ($category->description)
                        <p class="mt-3 text-perex text-muted">{{ $category->description }}</p>
                    @endif
                </div>

                <x-checklist.progress-bar :progress="$progress" data-progres-kategorie class="w-full shrink-0 menu:w-64" />
            </header>

            <div class="flex flex-col gap-10">
                @foreach ($category->sections as $section)
                    <section data-reveal>
                        <h3 class="mb-1 text-h3-sm font-extrabold tracking-[-.02em] text-ink">{{ $section->title }}</h3>

                        @if ($section->description)
                            <p class="mb-5 max-w-[64ch] text-perex text-muted">{{ $section->description }}</p>
                        @endif

                        <ul class="divide-y divide-ink/10 border-y border-ink/10">
                            @foreach ($section->items as $item)
                                <x-checklist.item :item="$item" :token="$checklist->public_token" />
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>

            <p class="mt-12">
                <a href="{{ route('checklist.show', $checklist->public_token) }}"
                   class="text-sm font-bold text-body transition-colors duration-300 ease-tavo hover:text-brick">
                    ← Zpět na přehled
                </a>
            </p>
        </div>
    </section>
</x-layout.document>
