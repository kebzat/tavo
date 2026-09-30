<x-filament-panels::page>
    @php
        $rows = $this->rows();
        $capacity = $this->capacity();
    @endphp

    <div class="flex items-center gap-2">
        <x-filament::button size="sm" color="gray" icon="heroicon-o-chevron-left" wire:click="previousMonth">Předchozí</x-filament::button>
        <x-filament::button size="sm" color="gray" icon="heroicon-o-chevron-right" icon-position="after" wire:click="nextMonth">Další</x-filament::button>
    </div>

    <x-filament::section>
        <x-slot name="heading">K fakturaci celkem {{ $this->total() }}</x-slot>
        <x-slot name="description">Paušál plus hodiny nad rámec paušálu sazbou z nastavení klienta (Cíle a paušál). Nefakturovatelná práce se nepočítá.</x-slot>

        @if ($rows->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">V tomhle měsíci žádný paušál ani zapsaný čas.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-max text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-950 dark:border-white/10 dark:text-white">
                            <th class="px-3 py-2 font-semibold">Klient</th>
                            <th class="px-3 py-2 text-right font-semibold">Paušál</th>
                            <th class="px-3 py-2 text-right font-semibold">Odpracováno</th>
                            <th class="px-3 py-2 text-right font-semibold">Navíc</th>
                            <th class="px-3 py-2 text-right font-semibold">Za práci navíc</th>
                            <th class="px-3 py-2 text-right font-semibold">Celkem</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($rows as $row)
                            <tr wire:key="billing-{{ $row['id'] }}">
                                <td class="px-3 py-2"><a href="{{ $row['url'] }}" class="font-medium text-gray-950 hover:text-primary-600 dark:text-white">{{ $row['name'] }}</a></td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $row['fee'] }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $row['hours'] }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $row['extra'] }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">
                                    {{ $row['extra_amount'] }}
                                    @if ($row['missing_rate'])
                                        <span class="block text-xs text-danger-600">chybí sazba</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right font-semibold tabular-nums text-gray-950 dark:text-white">{{ $row['total'] }}</td>
                                <td class="px-3 py-2 text-right">
                                    @if ($row['uninvoiced'] > 0)
                                        <x-filament::button size="xs" color="gray" wire:click="markInvoiced({{ $row['id'] }})" wire:confirm="Označit všechny fakturovatelné záznamy klienta v tomhle měsíci jako vyfakturované?">
                                            Vyfakturováno
                                        </x-filament::button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    @if ($capacity)
        <x-filament::section>
            <x-slot name="heading">Kdo kolik odpracoval</x-slot>
            <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($capacity as $person)
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $person['name'] }}</dt>
                        <dd class="text-2xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ $person['billable'] }}</dd>
                        <dd class="text-xs text-gray-500 dark:text-gray-400">a {{ $person['other'] }} nefakturovatelně</dd>
                    </div>
                @endforeach
            </dl>
        </x-filament::section>
    @endif
</x-filament-panels::page>
