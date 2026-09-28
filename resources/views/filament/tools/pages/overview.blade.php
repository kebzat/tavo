<x-filament-panels::page>
    @php
        $kpi = $this->kpi();
        $rows = $this->rows();
        $chart = $this->chart();
        $chartMax = $this->chartMax();
        $log = $this->outreachLog();
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

        <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-7">
            @foreach ($rows as $row)
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $row['label'] }}</dt>
                    <dd class="text-2xl font-semibold text-gray-950 dark:text-white">{{ $row['value'] }}</dd>
                    @if ($row['note'])
                        <dd class="text-xs text-gray-500 dark:text-gray-400">{{ $row['note'] }}</dd>
                    @endif
                </div>
            @endforeach
        </dl>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Oslovené firmy ({{ $log->count() }})</x-slot>
        <x-slot name="description">Od posledního oslovení. Reakce je z výsledku zapsané aktivity, jinak ze stavu firmy.</x-slot>

        <div class="overflow-x-auto">
            <table class="w-full min-w-max text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-white/10">
                        <th class="px-3 py-2 font-semibold text-gray-950 dark:text-white">Firma</th>
                        <th class="px-3 py-2 font-semibold text-gray-950 dark:text-white">Osloveno</th>
                        <th class="px-3 py-2 font-semibold text-gray-950 dark:text-white">Naposledy</th>
                        <th class="px-3 py-2 text-right font-semibold text-gray-950 dark:text-white">Kontaktů</th>
                        <th class="px-3 py-2 font-semibold text-gray-950 dark:text-white">Reakce</th>
                        <th class="px-3 py-2 font-semibold text-gray-950 dark:text-white">Stav</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($log as $line)
                        <tr class="border-b border-gray-100 last:border-0 dark:border-white/5">
                            <td class="px-3 py-2">
                                <a href="{{ $line['url'] }}" class="font-medium text-gray-950 hover:underline dark:text-white">{{ $line['name'] }}</a>
                            </td>
                            <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $line['first_at']->format('j. n. Y') }}</td>
                            <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $line['last_at']->format('j. n.') }} · {{ $line['channel'] }}</td>
                            <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-400">{{ $line['touches'] }}</td>
                            <td class="px-3 py-2">
                                <x-filament::badge :color="$line['reaction_color']" size="sm">{{ $line['reaction'] }}</x-filament::badge>
                            </td>
                            <td class="px-3 py-2">
                                <x-filament::badge :color="$line['status_color']" size="sm">{{ $line['status'] }}</x-filament::badge>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-4 text-gray-500 dark:text-gray-400">
                                Zatím jsme nikoho neoslovili. Oslovení se sem propíše, jakmile ho zapíšete u firmy.
                            </td>
                        </tr>
                    @endforelse
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
</x-filament-panels::page>
