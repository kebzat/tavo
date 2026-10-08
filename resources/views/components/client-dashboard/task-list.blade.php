@props([
    'rows' => [],
])

{{-- Seznam úkolů na přehledu spolupráce. Řádky skládá App\Support\ClientDashboard,
     tady se jen vysází. Bez řádků se nevykreslí. --}}
@if ($rows)
    <ul {{ $attributes->class('divide-y divide-ink/10 border-y border-ink/10') }}>
        @foreach ($rows as $row)
            <li class="flex flex-col gap-3 py-5 menu:flex-row menu:items-start menu:justify-between menu:gap-10 print:break-inside-avoid">
                <div class="min-w-0 max-w-[68ch]">
                    <p class="text-lg font-extrabold tracking-[-.01em] text-ink">{{ $row['title'] }}</p>

                    @if ($row['description'])
                        {{-- Popis je HTML z editoru, očištěný v ClientTask::descriptionHtml(). --}}
                        <div class="mt-1.5 text-[15px] leading-relaxed text-body [&_a]:font-semibold [&_a]:text-brick [&_a]:underline [&_a]:underline-offset-2 [&_a]:break-words [&_li]:mt-1 [&_ol]:mt-2 [&_ol]:list-decimal [&_ol]:pl-5 [&_p+p]:mt-2 [&_strong]:font-bold [&_strong]:text-ink [&_ul]:mt-2 [&_ul]:list-disc [&_ul]:pl-5">{{ $row['description'] }}</div>
                    @endif

                    @if ($row['area'] || $row['status'])
                        <p class="mt-3 flex flex-wrap gap-2">
                            @if ($row['status'])
                                <span class="rounded-pill px-3 py-1 text-xs font-bold {{ $row['status_classes'] }}">{{ $row['status'] }}</span>
                            @endif
                            @if ($row['area'])
                                <span class="rounded-pill px-3 py-1 text-xs font-bold {{ $row['area_classes'] }}">{{ $row['area'] }}</span>
                            @endif
                        </p>
                    @endif
                </div>

                @if ($row['hours'] || $row['people'])
                    <div class="flex shrink-0 items-baseline gap-3 menu:flex-col menu:items-end menu:gap-0.5">
                        @if ($row['hours'])
                            <p class="text-xl font-extrabold tabular-nums text-ink">{{ $row['hours'] }}</p>
                        @endif
                        @if ($row['people'])
                            <p class="text-sm text-muted">{{ $row['people'] }}</p>
                        @endif
                    </div>
                @endif
            </li>
        @endforeach
    </ul>
@endif
