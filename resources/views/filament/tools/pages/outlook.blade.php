<x-filament-panels::page>
    <div class="flex justify-end">
        <x-filament::tabs>
            @foreach ($filters as $filter)
                <x-filament::tabs.item :active="$filter['active']" wire:click="showArea('{{ $filter['key'] }}')">
                    {{ $filter['label'] }}
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>
    </div>

    @if ($rows->isEmpty())
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">V téhle oblasti zatím žádný klient s paušálem. Paušál se zadává u klienta v sekci Pravidelná spolupráce.</p>
        </x-filament::section>
    @else
        <div class="grid gap-4 sm:grid-cols-3">
            <x-filament::section>
                <p class="text-sm text-gray-500 dark:text-gray-400">Tento měsíc domluveno</p>
                <p class="text-2xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ $summary['now'] }}</p>
            </x-filament::section>
            <x-filament::section>
                <p class="text-sm text-gray-500 dark:text-gray-400">Průměr na měsíc, příštích 12 měsíců</p>
                <p class="text-2xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ $summary['average'] }}</p>
            </x-filament::section>
            <x-filament::section>
                <p class="text-sm text-gray-500 dark:text-gray-400">Nejslabší měsíc: {{ $summary['weakest_label'] }}</p>
                <p class="text-2xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ $summary['weakest'] }}</p>
                @if ($summary['gap'])
                    <p class="mt-1 text-sm text-danger-600 dark:text-danger-400">Do dnešní úrovně chybí {{ $summary['gap'] }} měsíčně</p>
                @endif
            </x-filament::section>
        </div>

        <x-filament::section>
            <x-slot name="heading">Po měsících</x-slot>
            <x-slot name="description">Domluvené paušály plné, předběžné světlé. Pod měsícem rozdíl domluvených oproti tomuto měsíci.</x-slot>

            <div class="flex items-end gap-1.5 sm:gap-3">
                @foreach ($totals as $month)
                    <div class="flex min-w-0 flex-1 flex-col items-center gap-1.5">
                        <span class="hidden h-4 whitespace-nowrap text-xs font-semibold text-gray-950 sm:block dark:text-white">{{ $month['top'] }}</span>

                        {{-- Výšky jsou poměr k nejvyššímu měsíci, proto inline styl. --}}
                        <div
                            class="flex h-48 w-full max-w-14 flex-col justify-end overflow-hidden rounded-t-md"
                            title="Domluveno {{ $month['confirmed'] }} · předběžně {{ $month['tentative'] }}"
                        >
                            <div class="w-full bg-success-200 dark:bg-success-500/30" style="height: {{ $month['tentative_height'] }}%"></div>
                            <div class="w-full bg-success-500" style="height: {{ $month['confirmed_height'] }}%"></div>
                        </div>

                        <span @class([
                            'text-xs',
                            'font-semibold text-gray-950 dark:text-white' => $month['current'],
                            'text-gray-500 dark:text-gray-400' => ! $month['current'],
                        ])>{{ $month['short'] }}</span>
                        <span @class([
                            'hidden h-4 whitespace-nowrap text-xs tabular-nums sm:block',
                            'text-danger-600 dark:text-danger-400' => $month['down'],
                            'text-success-600 dark:text-success-400' => ! $month['down'],
                        ])>{{ $month['diff'] }}</span>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 flex flex-wrap gap-4 text-xs text-gray-600 dark:text-gray-400">
                <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-success-500"></span> Domluveno</span>
                <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-success-200 dark:bg-success-500/30"></span> Předběžně</span>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Kdo kolik platí</x-slot>
            <x-slot name="description">Hodiny nad paušál a jednorázové zakázky tu nejsou, dopředu je neznáme. Rozjednané obchody jsou v Přehledu.</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full min-w-max text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-950 dark:border-white/10 dark:text-white">
                            <th class="sticky left-0 bg-white px-3 py-2 font-semibold dark:bg-gray-900">Klient</th>
                            @foreach ($months as $month)
                                <th @class([
                                    'px-3 py-2 text-right font-semibold',
                                    'text-primary-600 dark:text-primary-400' => $month['current'],
                                ]) title="{{ $month['label'] }}">{{ $month['short'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($rows as $row)
                            <tr>
                                <td class="sticky left-0 bg-white px-3 py-2 dark:bg-gray-900">
                                    <a href="{{ $row['url'] }}" class="font-medium text-gray-950 hover:text-primary-600 dark:text-white">{{ $row['name'] }}</a>
                                    @if ($row['note'])
                                        <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $row['note'] }}</span>
                                    @endif
                                </td>
                                @foreach ($row['cells'] as $cell)
                                    <td @class([
                                        'px-3 py-2 text-right tabular-nums',
                                        'text-gray-300 dark:text-gray-600' => $cell['empty'],
                                        'italic text-warning-600 dark:text-warning-400' => $cell['tentative'],
                                        'text-gray-950 dark:text-white' => ! $cell['empty'] && ! $cell['tentative'],
                                    ]) title="{{ $cell['title'] }}">{{ $cell['amount'] }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-gray-200 dark:border-white/10">
                            <th class="sticky left-0 bg-white px-3 py-2 font-semibold text-gray-950 dark:bg-gray-900 dark:text-white">Domluveno</th>
                            @foreach ($totals as $month)
                                <td class="px-3 py-2 text-right font-semibold tabular-nums text-gray-950 dark:text-white">{{ $month['confirmed'] }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <th class="sticky left-0 bg-white px-3 py-2 font-normal text-gray-500 dark:bg-gray-900 dark:text-gray-400">Včetně předběžných</th>
                            @foreach ($totals as $month)
                                <td class="px-3 py-2 text-right tabular-nums text-gray-500 dark:text-gray-400">{{ $month['total'] }}</td>
                            @endforeach
                        </tr>
                    </tfoot>
                </table>
            </div>

            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Kurzívou předběžné částky. Najetím myší na buňku uvidíte, kolik je domluveno a kolik předběžně.</p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
