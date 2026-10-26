<x-filament-panels::page>
    @php
        $cards = $this->cards();
        $tileOptions = $this->tileOptions();
        $summary = $this->summary();
        $through = $this->dataThrough();
    @endphp

    {{-- Ranní souhrn: čemu věřit, kde zasáhnout, na co nezapomenout. --}}
    <div class="flex flex-wrap items-center gap-2 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <span class="mr-1 text-sm font-semibold text-gray-950 dark:text-white">Dnes ráno:</span>
        @forelse ($summary as $item)
            @if ($item['url'])
                <a href="{{ $item['url'] }}" class="contents">
                    <x-filament::badge :color="$item['color']" size="lg">{{ $item['count'] }} {{ $item['label'] }}</x-filament::badge>
                </a>
            @else
                <x-filament::badge :color="$item['color']" size="lg">{{ $item['count'] }} {{ $item['label'] }}</x-filament::badge>
            @endif
        @empty
            <span class="text-sm text-gray-600 dark:text-gray-400">Nic nehoří: data jsou celá, výkon v mezích, žádný termín ani report nečeká.</span>
        @endforelse
        @if ($through)
            <span class="ml-auto text-xs text-gray-500 dark:text-gray-400">Data u všech klientů kompletní nejméně do {{ $through }}</span>
        @endif
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
        @include('filament.tools.pages.ads.partials.period', ['note' => $this->periodNote()])

        @if ($cards->count() > 1)
            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                Řadit:
                @foreach (['alerts' => 'podle upozornění', 'spend' => 'podle útraty', 'name' => 'podle názvu'] as $key => $label)
                    <button type="button" wire:click="setSort('{{ $key }}')" @class([
                        'rounded-md px-2 py-1',
                        'bg-gray-100 font-semibold text-gray-950 dark:bg-white/10 dark:text-white' => $sort === $key,
                        'hover:text-gray-950 dark:hover:text-white' => $sort !== $key,
                    ])>{{ $label }}</button>
                @endforeach
            </div>
        @endif
    </div>

    @if ($cards->isEmpty())
        <x-filament::section>
            <div class="mx-auto max-w-xl py-8 text-center">
                <x-filament::icon icon="heroicon-o-presentation-chart-line" class="mx-auto size-10 text-gray-400" />
                <h2 class="mt-4 text-lg font-semibold text-gray-950 dark:text-white">Zatím tu není žádný klient</h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Klient nasdílí svůj reklamní účet Business Manageru Taveo jako partnerovi. Pak ho tlačítkem
                    „Přidat klienta“ propojíte a čísla se začnou každé ráno stahovat.
                </p>
            </div>
        </x-filament::section>
    @else
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 2xl:grid-cols-3">
            @foreach ($cards as $card)
                {{-- Celá karta je odkaz přes roztažený ::after u názvu. Tři tečky u čísel leží nad ním (z-10), ať neotevírají detail. --}}
                <div wire:key="ads-card-{{ $card['id'] }}" @class([
                    'group relative flex flex-col rounded-xl bg-white p-5 shadow-sm ring-1 transition hover:shadow-md dark:bg-gray-900',
                    'ring-danger-300 dark:ring-danger-500/50' => $card['worst']?->value === 'critical',
                    'ring-warning-300 dark:ring-warning-500/50' => $card['worst']?->value === 'warning',
                    'ring-gray-950/5 dark:ring-white/10' => ! in_array($card['worst']?->value, ['critical', 'warning'], true),
                ])>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="truncate text-base font-semibold text-gray-950 group-hover:text-primary-600 dark:text-white dark:group-hover:text-primary-400">
                                <a href="{{ $card['url'] }}" class="after:absolute after:inset-0 after:rounded-xl">{{ $card['name'] }}</a>
                            </h2>
                            <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $card['template'] }} · {{ $card['accounts'] }}</p>
                        </div>

                        <div class="flex shrink-0 items-center gap-1.5">
                            <x-filament::badge :color="$card['status']->color()" icon="heroicon-m-circle-stack" :tooltip="$card['status']->sentence()">{{ $card['status']->badge() }}</x-filament::badge>
                            @if ($card['alerts'] > 0)
                                <x-filament::badge :color="$card['worst_color']" icon="heroicon-m-bell-alert">
                                    {{ $card['alerts'] }}
                                </x-filament::badge>
                            @endif
                        </div>
                    </div>

                    @if ($card['top_alert'])
                        <p @class([
                            'mt-3 flex items-start gap-1.5 text-sm',
                            'text-danger-700 dark:text-danger-400' => $card['worst']?->value === 'critical',
                            'text-warning-700 dark:text-warning-400' => $card['worst']?->value !== 'critical',
                        ])>
                            <x-filament::icon icon="heroicon-m-bell-alert" class="mt-0.5 size-4 shrink-0" />
                            <span class="min-w-0">{{ $card['top_alert'] }}</span>
                        </p>
                    @endif

                    @if ($card['targets'])
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($card['targets'] as $target)
                                <span @class([
                                    'rounded-md px-2 py-1 text-xs',
                                    'bg-success-50 text-success-800 dark:bg-success-500/10 dark:text-success-300' => $target['ok'] === true,
                                    'bg-danger-50 text-danger-800 dark:bg-danger-500/10 dark:text-danger-300' => $target['ok'] === false,
                                    'bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-gray-300' => $target['ok'] === null,
                                ])>{{ $target['label'] }} <span class="font-semibold tabular-nums">{{ $target['value'] }}</span> · cíl {{ $target['target'] }}</span>
                            @endforeach
                        </div>
                    @endif

                    @if ($card['has_data'])
                        <dl class="mt-4 grid grid-cols-3 gap-x-3 gap-y-3">
                            @foreach ($card['metrics'] as $slot => $metric)
                                <div class="min-w-0" title="{{ collect([$metric['description'], $metric['hint']])->filter()->implode(' ') }}">
                                    <dt class="flex items-center gap-0.5 text-xs text-gray-500 dark:text-gray-400">
                                        <span class="min-w-0 truncate">{{ $metric['label'] }}</span>
                                        <x-filament::dropdown placement="bottom-start" max-height="20rem" width="xs" shift class="-my-1 shrink-0">
                                            <x-slot name="trigger" class="relative z-10">
                                                <button type="button" title="Vybrat číslo" class="flex size-5 items-center justify-center rounded text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-500 dark:hover:bg-white/10 dark:hover:text-gray-200">
                                                    <x-filament::icon icon="heroicon-m-ellipsis-vertical" class="size-4" />
                                                    <span class="sr-only">Vybrat číslo na pozici {{ $slot + 1 }}</span>
                                                </button>
                                            </x-slot>

                                            <x-filament::dropdown.header>Pozice {{ $slot + 1 }}, šablona {{ $card['template'] }} (všichni klienti a skupiny s tímhle cílem)</x-filament::dropdown.header>
                                            <x-filament::dropdown.list>
                                                @foreach ($tileOptions as $key => $label)
                                                    <x-filament::dropdown.list.item
                                                        wire:click="setOverviewTile({{ $slot }}, '{{ $key }}', '{{ $card['goal_key'] }}')"
                                                        x-on:click="close()"
                                                        :color="$metric['key'] === $key ? 'primary' : 'gray'"
                                                        :icon="$metric['key'] === $key ? 'heroicon-m-check' : null"
                                                    >{{ $label }}</x-filament::dropdown.list.item>
                                                @endforeach
                                            </x-filament::dropdown.list>
                                        </x-filament::dropdown>
                                    </dt>
                                    <dd class="truncate text-base font-semibold tabular-nums text-gray-950 dark:text-white">{{ $metric['value'] }}</dd>
                                    @if ($metric['change'])
                                        <dd @class([
                                            'text-xs tabular-nums',
                                            'text-success-600 dark:text-success-400' => $metric['tone'] === 'good',
                                            'text-danger-600 dark:text-danger-400' => $metric['tone'] === 'bad',
                                            'text-gray-500 dark:text-gray-400' => $metric['tone'] === 'neutral',
                                        ])>{{ $metric['change'] }}</dd>
                                    @endif
                                </div>
                            @endforeach
                        </dl>

                        <div class="mt-4 grid grid-cols-2 gap-3">
                            <div>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400">Útrata po dnech</p>
                                <x-ads.sparkline :values="$card['spark_spend']" class="text-primary-500" />
                            </div>
                            <div>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400">{{ $card['goal'] }} po dnech</p>
                                <x-ads.sparkline :values="$card['spark_conversions']" class="text-gray-700 dark:text-gray-300" />
                            </div>
                        </div>
                    @elseif ($card['covered'])
                        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Data jsou stažená, za tohle období žádná útrata. Kampaně neběžely.</p>
                    @else
                        <p class="mt-4 text-sm text-warning-700 dark:text-warning-400">Za tohle období čísla chybí, není to nula. {{ $card['status']->badge() }}.</p>
                    @endif

                    @if ($card['groups'])
                        <div class="relative z-10 mt-4 border-t border-gray-100 pt-3 dark:border-white/5">
                            <p class="text-[11px] text-gray-500 dark:text-gray-400">Skupiny kampaní</p>
                            <ul class="mt-1 space-y-1 text-xs">
                                @foreach ($card['groups'] as $group)
                                    <li class="flex flex-wrap items-baseline justify-between gap-x-2">
                                        <a href="{{ $group['url'] }}" class="font-medium text-gray-950 hover:text-primary-600 dark:text-white">{{ $group['name'] }} <span class="font-normal text-gray-500">· {{ $group['template'] }}</span></a>
                                        <span class="tabular-nums text-gray-600 dark:text-gray-400">
                                            {{ $group['spend'] }}
                                            @foreach ($group['targets'] as $target)
                                                · <span @class(['text-success-600 dark:text-success-400' => $target['ok'] === true, 'text-danger-600 dark:text-danger-400' => $target['ok'] === false])>{{ $target['label'] }} {{ $target['value'] }}</span>
                                            @endforeach
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($card['budget'])
                        <div class="mt-4 border-t border-gray-100 pt-3 dark:border-white/5">
                            <div class="flex items-baseline justify-between gap-2 text-xs">
                                <span class="text-gray-500 dark:text-gray-400">Rozpočet v měsíci: <span class="font-semibold text-gray-950 dark:text-white">{{ $card['budget']['label'] }}</span></span>
                                @if ($card['budget']['pace'])
                                    <span class="shrink-0 text-gray-500 dark:text-gray-400">{{ $card['budget']['pace'] }}</span>
                                @endif
                            </div>
                            {{-- Šířka a poloha značky jsou procenta z dat, proto inline styl. --}}
                            <div class="relative mt-1.5 h-2 rounded-full bg-gray-100 dark:bg-white/10">
                                <div class="absolute inset-y-0 left-0 rounded-full bg-primary-500" style="width: {{ $card['budget']['percent'] }}%"></div>
                                <div class="absolute -inset-y-0.5 w-0.5 rounded-full bg-gray-950 dark:bg-white" style="left: {{ $card['budget']['expected'] }}%" title="Plán k dnešku"></div>
                            </div>
                            @if ($card['budget']['note'])
                                <p class="mt-1 text-[11px] text-warning-600 dark:text-warning-400">{{ $card['budget']['note'] }}</p>
                            @endif
                        </div>
                    @endif

                    @if ($card['next_event'])
                        <p @class([
                            'mt-3 flex items-start gap-1.5 text-xs',
                            'text-danger-600 dark:text-danger-400' => $card['next_event']['overdue'],
                            'text-gray-600 dark:text-gray-400' => ! $card['next_event']['overdue'],
                        ])>
                            <x-filament::icon icon="heroicon-m-calendar-days" class="size-4 shrink-0" />
                            <span class="min-w-0">{{ $card['next_event']['when'] }}: {{ $card['next_event']['title'] }}</span>
                        </p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
