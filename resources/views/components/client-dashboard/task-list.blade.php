@props([
    'rows' => [],
    // Kolik úkolů na stránku. Bez čísla (nebo když se vejdou) se ukáže celý seznam.
    'perPage' => null,
])

{{-- Seznam úkolů na přehledu spolupráce. Řádky skládá App\Support\ClientDashboard,
     tady se jen vysází. Bez řádků se nevykreslí. Popis je zkrácený na dva řádky,
     celý po kliknutí. Bez JS a při tisku se ukáže všechno. --}}
@if ($rows)
    <div @if ($perPage && count($rows) > $perPage) x-data="tavoTaskPages({{ $perPage }}, {{ count($rows) }})" @endif class="scroll-mt-6">
        <ul {{ $attributes->class('divide-y divide-ink/10 border-y border-ink/10') }}>
            @foreach ($rows as $row)
                <li x-data="tavoTaskRow"
                    @if ($perPage && count($rows) > $perPage) x-show="shown({{ $loop->index }})" @endif
                    class="flex flex-col gap-2 py-4 menu:flex-row menu:items-start menu:justify-between menu:gap-8 print:flex! print:break-inside-avoid">
                    <div class="min-w-0 max-w-[68ch]">
                        <p class="text-[17px] leading-snug font-extrabold tracking-[-.01em] text-ink">{{ $row['title'] }}</p>

                        @if ($row['description'])
                            {{-- Popis je HTML z editoru, očištěný v ClientTask::descriptionHtml(). --}}
                            <div x-ref="description" x-bind:class="open ? '' : 'line-clamp-2'"
                                 class="mt-1 text-sm leading-relaxed text-body print:line-clamp-none [&_a]:font-semibold [&_a]:text-brick [&_a]:underline [&_a]:underline-offset-2 [&_a]:break-words [&_li]:mt-1 [&_ol]:mt-2 [&_ol]:list-decimal [&_ol]:pl-5 [&_p+p]:mt-2 [&_strong]:font-bold [&_strong]:text-ink [&_ul]:mt-2 [&_ul]:list-disc [&_ul]:pl-5">{{ $row['description'] }}</div>

                            <button type="button" x-cloak x-show="long" x-on:click="open = ! open" x-bind:aria-expanded="open"
                                    class="mt-1 text-sm font-bold text-ink underline decoration-brick underline-offset-4 print:hidden"
                                    x-text="open ? @js(text('client_dashboard.less', 'Méně')) : @js(text('client_dashboard.more', 'Celý popis'))"></button>
                        @endif
                    </div>

                    @if ($row['area'] || $row['status'] || $row['hours'] || $row['people'])
                        <div class="flex shrink-0 flex-wrap items-center gap-x-3 gap-y-1.5 menu:flex-col menu:items-end">
                            @if ($row['area'] || $row['status'])
                                <p class="flex flex-wrap gap-1.5">
                                    @if ($row['status'])
                                        <span class="rounded-pill px-2.5 py-0.5 text-xs font-bold {{ $row['status_classes'] }}">{{ $row['status'] }}</span>
                                    @endif
                                    @if ($row['area'])
                                        <span class="rounded-pill px-2.5 py-0.5 text-xs font-bold {{ $row['area_classes'] }}">{{ $row['area'] }}</span>
                                    @endif
                                </p>
                            @endif
                            @if ($row['hours'] || $row['people'])
                                <p class="text-sm text-muted">
                                    @if ($row['hours'])
                                        <span class="font-extrabold text-ink tabular-nums">{{ $row['hours'] }}</span>
                                    @endif
                                    @if ($row['hours'] && $row['people'])
                                        ·
                                    @endif
                                    {{ $row['people'] }}
                                </p>
                            @endif
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>

        @if ($perPage && count($rows) > $perPage)
            <nav x-cloak class="mt-4 flex flex-wrap items-center justify-between gap-3 print:hidden" aria-label="{{ text('client_dashboard.pages', 'Stránky') }}">
                <p class="text-sm text-muted tabular-nums" x-text="range()"></p>

                <div class="flex flex-wrap items-center gap-1.5">
                    <button type="button" x-on:click="go(page - 1)" x-bind:disabled="page === 1"
                            class="rounded-pill border border-ink/20 px-3.5 py-1.5 text-sm font-bold text-ink transition-colors duration-300 ease-tavo hover:border-ink hover:bg-ink/6 disabled:pointer-events-none disabled:opacity-35"
                            aria-label="{{ text('client_dashboard.page_prev', 'Předchozí') }}">←</button>

                    <template x-for="number in pages" x-bind:key="number">
                        <button type="button" x-on:click="go(number)" x-text="number"
                                x-bind:aria-current="number === page ? 'page' : null"
                                x-bind:class="number === page ? 'border-ink bg-ink text-cream' : 'border-ink/20 text-ink hover:border-ink hover:bg-ink/6'"
                                class="min-w-9 rounded-pill border px-3 py-1.5 text-sm font-bold tabular-nums transition-colors duration-300 ease-tavo"></button>
                    </template>

                    <button type="button" x-on:click="go(page + 1)" x-bind:disabled="page === pages"
                            class="rounded-pill border border-ink/20 px-3.5 py-1.5 text-sm font-bold text-ink transition-colors duration-300 ease-tavo hover:border-ink hover:bg-ink/6 disabled:pointer-events-none disabled:opacity-35"
                            aria-label="{{ text('client_dashboard.page_next', 'Další') }}">→</button>
                </div>
            </nav>
        @endif
    </div>
@endif
