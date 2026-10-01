<x-filament-panels::page>
    @php
        $cards = $this->cards();
    @endphp

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
        <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
            @foreach ($cards as $card)
                <a href="{{ $card['url'] }}" wire:key="ads-card-{{ $card['id'] }}" @class([
                    'group flex flex-col rounded-xl bg-white p-5 shadow-sm ring-1 transition hover:shadow-md dark:bg-gray-900',
                    'ring-danger-300 dark:ring-danger-500/50' => $card['worst']?->value === 'critical',
                    'ring-warning-300 dark:ring-warning-500/50' => $card['worst']?->value === 'warning',
                    'ring-gray-950/5 dark:ring-white/10' => ! in_array($card['worst']?->value, ['critical', 'warning'], true),
                ])>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="truncate text-base font-semibold text-gray-950 group-hover:text-primary-600 dark:text-white dark:group-hover:text-primary-400">{{ $card['name'] }}</h2>
                            <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $card['accounts'] }}</p>
                        </div>

                        <div class="flex shrink-0 items-center gap-1.5">
                            @if ($card['sync_error'])
                                <x-filament::badge color="danger" icon="heroicon-m-arrow-path">Nestahuje se</x-filament::badge>
                            @endif
                            @if ($card['alerts'] > 0)
                                <x-filament::badge :color="$card['worst_color']" icon="heroicon-m-bell-alert">
                                    {{ $card['alerts'] }}
                                </x-filament::badge>
                            @endif
                        </div>
                    </div>

                    @if ($card['has_data'])
                        <dl class="mt-4 grid grid-cols-3 gap-x-3 gap-y-3">
                            @foreach ($card['metrics'] as $metric)
                                <div class="min-w-0">
                                    <dt class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $metric['label'] }}</dt>
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
                    @else
                        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Za tohle období žádná útrata.</p>
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
                        </div>
                    @endif
                </a>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
