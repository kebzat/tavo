<x-filament-panels::page>
    @php
        $kpi = $this->kpi();
        $rows = $this->rows();
        $chart = $this->chart();
        $chartMax = $this->chartMax();
    @endphp

    <x-filament::section>
        <x-slot name="heading">
            Týden {{ $kpi->from->format('j. n.') }} – {{ $kpi->to->format('j. n. Y') }}
        </x-slot>

        <x-slot name="afterHeader">
            <div class="flex items-center gap-2">
                <x-filament::button size="sm" color="gray" icon="heroicon-o-chevron-left" wire:click="previousWeek">
                    Předchozí
                </x-filament::button>

                @unless ($this->isCurrentWeek())
                    <x-filament::button size="sm" color="gray" wire:click="thisWeek">Tento týden</x-filament::button>
                @endunless

                <x-filament::button size="sm" color="gray" icon="heroicon-o-chevron-right" icon-position="after" wire:click="nextWeek">
                    Další
                </x-filament::button>
            </div>
        </x-slot>

        <div class="overflow-x-auto">
            <table class="w-full min-w-max text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-white/10">
                        <th class="px-3 py-2 font-semibold text-gray-950 dark:text-white">Ukazatel</th>
                        <th class="px-3 py-2 text-right font-semibold text-gray-950 dark:text-white">Skutečnost</th>
                        <th class="px-3 py-2 text-right font-semibold text-gray-950 dark:text-white">Cíl</th>
                        <th class="px-3 py-2 font-semibold text-gray-950 dark:text-white">Stav</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-b border-gray-100 last:border-0 dark:border-white/5">
                            <td class="px-3 py-2 text-gray-950 dark:text-white">
                                {{ $row['label'] }}
                                @if ($row['note'])
                                    <span class="text-gray-500 dark:text-gray-400">· {{ $row['note'] }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right text-lg font-semibold text-gray-950 dark:text-white">
                                {{ $row['value'] }}
                            </td>
                            <td class="px-3 py-2 text-right text-gray-500 dark:text-gray-400">
                                {{ $row['goal'] ?? '—' }}
                            </td>
                            <td class="px-3 py-2">
                                @if ($row['met'] === null)
                                    <span class="text-gray-400 dark:text-gray-500">bez cíle</span>
                                @elseif ($row['met'])
                                    <x-filament::badge color="success" size="sm">splněno</x-filament::badge>
                                @else
                                    <x-filament::badge color="danger" size="sm">
                                        chybí {{ $row['goal'] - $row['value'] }}
                                    </x-filament::badge>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Posledních 8 týdnů</x-slot>
        <x-slot name="description">Oslovení, odpovědi a odeslané nabídky vedle sebe.</x-slot>

        <div class="flex items-end gap-3 overflow-x-auto pb-2">
            @foreach ($chart as $week)
                <div class="flex min-w-16 flex-1 flex-col items-center gap-2">
                    {{--
                        Výšky sloupců jsou poměr k nejvyšší hodnotě v grafu, proto
                        jdou inline stylem. Tailwind by třídu složenou za běhu
                        stejně nenašel a do CSS by se nedostala.
                    --}}
                    <div class="flex h-40 w-full items-end justify-center gap-1">
                        <div
                            class="w-3 rounded-t bg-primary-500"
                            style="height: {{ max(2, round($week['outreach'] / $chartMax * 100)) }}%"
                            title="Oslovení: {{ $week['outreach'] }}"
                        ></div>
                        <div
                            class="w-3 rounded-t bg-success-500"
                            style="height: {{ max(2, round($week['replies'] / $chartMax * 100)) }}%"
                            title="Odpovědi: {{ $week['replies'] }}"
                        ></div>
                        <div
                            class="w-3 rounded-t bg-warning-500"
                            style="height: {{ max(2, round($week['proposals'] / $chartMax * 100)) }}%"
                            title="Nabídky: {{ $week['proposals'] }}"
                        ></div>
                    </div>

                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $week['label'] }}</span>
                </div>
            @endforeach
        </div>

        <div class="mt-4 flex flex-wrap gap-4 text-xs text-gray-600 dark:text-gray-400">
            <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-primary-500"></span> Oslovení</span>
            <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-success-500"></span> Odpovědi</span>
            <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-warning-500"></span> Nabídky</span>
        </div>
    </x-filament::section>

    <div class="grid gap-6 lg:grid-cols-2">
        @foreach ([['Podle zdroje', $this->bySource()], ['Podle segmentu', $this->bySegment()]] as [$heading, $breakdown])
            <x-filament::section>
                <x-slot name="heading">{{ $heading }}</x-slot>
                <x-slot name="description">Za celou dobu — konverze kanálu se z jednoho týdne poznat nedá.</x-slot>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-white/10">
                                <th class="px-3 py-2 font-semibold text-gray-950 dark:text-white">&nbsp;</th>
                                <th class="px-3 py-2 text-right font-semibold text-gray-950 dark:text-white">Firem</th>
                                <th class="px-3 py-2 text-right font-semibold text-gray-950 dark:text-white">Odpovědí</th>
                                <th class="px-3 py-2 text-right font-semibold text-gray-950 dark:text-white">Vyhráno</th>
                                <th class="px-3 py-2 text-right font-semibold text-gray-950 dark:text-white">Odezva</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($breakdown as $line)
                                <tr class="border-b border-gray-100 last:border-0 dark:border-white/5">
                                    <td class="px-3 py-2 text-gray-950 dark:text-white">{{ $line['label'] }}</td>
                                    <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-400">{{ $line['companies'] }}</td>
                                    <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-400">{{ $line['replies'] }}</td>
                                    <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-400">{{ $line['won'] }}</td>
                                    <td class="px-3 py-2 text-right font-medium text-gray-950 dark:text-white">
                                        {{ number_format($line['rate'], 1, ',', ' ') }} %
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-3 py-4 text-gray-500 dark:text-gray-400">
                                        Zatím není co počítat — přidej první firmy.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
