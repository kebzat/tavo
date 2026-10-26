{{--
    Denní graf útraty (sloupce) a konverzí (čára). Geometrii počítá
    App\View\Components\Ads\Chart, tady se jen kreslí. Výšky sloupců jsou
    v jednotkách viewBoxu, proto inline atributy místo tříd.
--}}
@if ($columns)
    <figure
        x-data="{ active: null }"
        @mouseleave="active = null"
        {{ $attributes->class(['relative']) }}
    >
        <div class="mb-3 flex flex-wrap items-center gap-x-5 gap-y-1 text-xs">
            <span class="flex items-center gap-1.5">
                <span class="size-2.5 rounded-sm" style="background: #db4b24"></span>
                {{ $bars['label'] }}@if ($barMax) <span class="opacity-60">· max {{ $barMax }}</span>@endif
            </span>
            @if ($linePath)
                <span class="flex items-center gap-1.5">
                    <span @class(['h-0.5 w-3.5 rounded-full bg-current', $lineClass])></span>
                    {{ $line['label'] }}@if ($lineMax) <span class="opacity-60">· max {{ $lineMax }}</span>@endif
                </span>
            @endif
            @if ($hasSettling)
                <span class="flex items-center gap-1.5">
                    <span class="size-2.5 rounded-sm" style="background: #db4b24; opacity: .4"></span>
                    ještě se dopočítává
                </span>
            @endif
            @if ($hasMissing)
                <span class="flex items-center gap-1.5">
                    <span class="size-2.5 rounded-sm border border-dashed border-current opacity-60"></span>
                    chybí data (není nula)
                </span>
            @endif
            @if ($hasMarkers)
                <span class="flex items-center gap-1.5">
                    <span class="flex size-4 items-center justify-center rounded-full bg-gray-950 text-[9px] font-bold text-white dark:bg-white dark:text-gray-950">K</span>
                    zásah · <span class="text-[11px]">◆</span> termín
                </span>
            @endif
        </div>

        <div class="relative" style="height: {{ $height }}px">
            <svg viewBox="{{ $viewBox() }}" preserveAspectRatio="none" class="absolute inset-0 h-full w-full overflow-visible" role="img" aria-label="{{ $bars['label'] }} a {{ mb_strtolower($line['label']) }} po dnech">
                <line x1="0" y1="100" x2="1000" y2="100" stroke="currentColor" stroke-opacity=".18" vector-effect="non-scaling-stroke" />
                <line x1="0" y1="54" x2="1000" y2="54" stroke="currentColor" stroke-opacity=".08" stroke-dasharray="4 4" vector-effect="non-scaling-stroke" />
                <line x1="0" y1="8" x2="1000" y2="8" stroke="currentColor" stroke-opacity=".08" stroke-dasharray="4 4" vector-effect="non-scaling-stroke" />

                @foreach ($columns as $i => $column)
                    @if ($column['state'] === 'missing')
                        <rect
                            x="{{ $column['x'] }}" y="8" width="{{ $column['width'] }}" height="92"
                            fill="currentColor" fill-opacity=".05"
                            stroke="currentColor" stroke-opacity=".35" stroke-dasharray="3 3" vector-effect="non-scaling-stroke"
                        />
                    @else
                        <rect
                            x="{{ $column['x'] }}" y="{{ 100 - $column['height'] }}"
                            width="{{ $column['width'] }}" height="{{ $column['height'] }}"
                            fill="#db4b24"
                            :fill-opacity="active === null || active === {{ $i }} ? {{ $column['state'] === 'settling' ? '0.4' : '0.85' }} : 0.25"
                            fill-opacity="{{ $column['state'] === 'settling' ? '0.4' : '0.85' }}"
                        />
                    @endif
                    @if ($column['marks'])
                        <line x1="{{ $column['x'] + $column['width'] / 2 }}" y1="0" x2="{{ $column['x'] + $column['width'] / 2 }}" y2="100" stroke="currentColor" stroke-opacity=".35" stroke-dasharray="2 3" vector-effect="non-scaling-stroke" />
                    @endif
                @endforeach

                @foreach ($lineSegments as $segment)
                    <polyline points="{{ $segment }}" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" @class([$lineClass]) />
                @endforeach
            </svg>

            {{-- Značky zásahů a termínů nad sloupcem. Poloha v procentech šířky, proto inline styl. --}}
            @foreach ($columns as $i => $column)
                @if ($column['marks'])
                    <div class="pointer-events-none absolute -top-2 flex -translate-x-1/2 gap-0.5" style="left: {{ round(($i + 0.5) / count($columns) * 100, 2) }}%">
                        @foreach ($column['marks'] as $mark)
                            <span @class([
                                'flex size-4 items-center justify-center rounded-full text-[9px] font-bold',
                                'bg-gray-950 text-white dark:bg-white dark:text-gray-950' => $mark['kind'] === 'change',
                                'bg-white text-gray-950 ring-1 ring-gray-950/30 dark:bg-gray-900 dark:text-white dark:ring-white/40' => $mark['kind'] !== 'change',
                            ])>{{ $mark['mark'] }}</span>
                        @endforeach
                    </div>
                @endif
            @endforeach

            {{-- Neviditelné sloupce přes celou výšku, ať se na tooltip dá najet i nad nízkým dnem. --}}
            <div class="absolute inset-0 flex">
                @foreach ($columns as $i => $column)
                    <div class="h-full flex-1" @mouseenter="active = {{ $i }}" @touchstart.passive="active = {{ $i }}"></div>
                @endforeach
            </div>

            @foreach ($columns as $i => $column)
                <div
                    x-cloak
                    x-show="active === {{ $i }}"
                    class="pointer-events-none absolute top-0 z-10 w-max max-w-72 -translate-x-1/2 rounded-lg bg-gray-950 px-3 py-2 text-xs text-white shadow-lg"
                    style="left: clamp(60px, {{ round(($i + 0.5) / count($columns) * 100, 2) }}%, calc(100% - 60px))"
                >
                    <p class="font-semibold">{{ $column['label'] }}</p>
                    @if ($column['state'] === 'missing')
                        <p>Chybí stažená data, není to nula.</p>
                    @else
                        <p>{{ $bars['label'] }}: {{ $column['bar'] }}</p>
                        @if ($linePath)
                            <p>{{ $line['label'] }}: {{ $column['line'] }}</p>
                        @endif
                        @if ($column['state'] === 'settling')
                            <p class="opacity-70">Meta ještě dopočítává, číslo se může změnit.</p>
                        @endif
                    @endif
                    @foreach ($column['marks'] as $mark)
                        <p class="mt-1 whitespace-normal border-t border-white/20 pt-1">{{ $mark['text'] }}</p>
                    @endforeach
                </div>
            @endforeach
        </div>

        <div class="relative mt-2 h-4 text-[11px] opacity-60">
            @foreach ($labelIndexes as $i)
                <span class="absolute -translate-x-1/2 whitespace-nowrap" style="left: {{ round(($i + 0.5) / count($columns) * 100, 2) }}%">{{ $columns[$i]['label'] }}</span>
            @endforeach
        </div>
    </figure>
@endif
