<x-filament-panels::page>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <x-filament::button size="sm" color="gray" icon="heroicon-o-chevron-left" wire:click="previousMonth">Předchozí</x-filament::button>
            <x-filament::button size="sm" color="gray" icon="heroicon-o-chevron-right" icon-position="after" wire:click="nextMonth">Další</x-filament::button>
        </div>

        <x-filament::tabs>
            @foreach ($filters as $filter)
                <x-filament::tabs.item :active="$filter['active']" wire:click="showArea('{{ $filter['key'] }}')">
                    {{ $filter['label'] }}
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">K fakturaci celkem</p>
            <p class="text-2xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ $total }}</p>
        </x-filament::section>
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">Vyfakturováno</p>
            <p class="text-2xl font-semibold tabular-nums text-success-600 dark:text-success-400">{{ $invoiced }}</p>
        </x-filament::section>
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">Zbývá vyfakturovat</p>
            <p @class([
                'text-2xl font-semibold tabular-nums',
                'text-danger-600 dark:text-danger-400' => $remaining_raw > 0,
                'text-gray-950 dark:text-white' => $remaining_raw <= 0,
            ])>{{ $remaining }}</p>
        </x-filament::section>
    </div>

    @foreach ($sections as $section)
        <x-filament::section wire:key="section-{{ $section['key'] }}">
            <x-slot name="heading">{{ $section['heading'] }}</x-slot>
            <x-slot name="description">{{ $section['summary'] }}</x-slot>

            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Paušály a hodiny</h3>

            @if ($section['rows']->isEmpty())
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">V tomhle měsíci žádný paušál ani zapsaný čas.</p>
            @else
                <div class="mt-2 overflow-x-auto">
                    <table class="w-full min-w-max text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-gray-950 dark:border-white/10 dark:text-white">
                                <th class="px-3 py-2 font-semibold">Klient</th>
                                <th class="px-3 py-2 text-right font-semibold">Paušál</th>
                                <th class="px-3 py-2 text-right font-semibold">Odpracováno</th>
                                <th class="px-3 py-2 text-right font-semibold">Navíc</th>
                                <th class="px-3 py-2 text-right font-semibold">Za práci navíc</th>
                                <th class="px-3 py-2 text-right font-semibold">Celkem</th>
                                <th class="px-3 py-2 font-semibold">Faktura</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($section['rows'] as $row)
                                <tr wire:key="billing-{{ $row['area'] }}-{{ $row['id'] }}">
                                    <td class="px-3 py-2">
                                        <a href="{{ $row['url'] }}" class="font-medium text-gray-950 hover:text-primary-600 dark:text-white">{{ $row['name'] }}</a>
                                        @if ($row['ads_url'])
                                            <a href="{{ $row['ads_url'] }}" class="block text-xs text-gray-500 hover:text-primary-600 dark:text-gray-400">Reklamy</a>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums">
                                        {{ $row['fee'] }}
                                        @if ($row['tentative'])
                                            <a href="{{ $row['url'] }}" class="block text-xs text-warning-600 hover:underline dark:text-warning-400">+ {{ $row['tentative'] }} předběžně, potvrďte</a>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $row['hours'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $row['extra'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">
                                        {{ $row['extra_amount'] }}
                                        @if ($row['missing_rate'])
                                            <span class="block text-xs text-danger-600">chybí sazba</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right font-semibold tabular-nums text-gray-950 dark:text-white">{{ $row['total'] }}</td>
                                    <td class="px-3 py-2">
                                        @if ($row['invoiced'])
                                            <div class="flex items-center gap-2">
                                                <x-filament::badge :color="$row['changed'] ? 'warning' : 'success'" :icon="$row['changed'] ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check'">
                                                    {{ $row['invoiced_note'] }}
                                                </x-filament::badge>
                                                <x-filament::link tag="button" size="xs" color="gray" wire:click="unmarkInvoiced({{ $row['id'] }}, '{{ $row['area'] }}')" wire:confirm="Vrátit klienta mezi nevyfakturované?">Zpět</x-filament::link>
                                            </div>
                                            @if ($row['changed'])
                                                <div class="mt-1 flex items-center gap-2 text-xs text-warning-600 dark:text-warning-400">
                                                    Od faktury přibyly hodiny nebo se změnil paušál.
                                                    <x-filament::link tag="button" size="xs" wire:click="markInvoiced({{ $row['id'] }}, '{{ $row['area'] }}')">Vyfakturováno i to</x-filament::link>
                                                </div>
                                            @endif
                                        @else
                                            <x-filament::button size="xs" color="gray" icon="heroicon-m-check" wire:click="markInvoiced({{ $row['id'] }}, '{{ $row['area'] }}')">
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

            @if ($section['deals']->isNotEmpty())
                <h3 class="mt-8 text-sm font-semibold text-gray-950 dark:text-white">Jednorázové zakázky</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">Vyhrané obchody z CRM kromě průběžné správy. Nevyfakturované tu visí, dokud je neoznačíte.</p>
                @include('filament.tools.pages.invoicing-deals', ['deals' => $section['deals']])
            @endif
        </x-filament::section>
    @endforeach

    @if ($unassigned['hours'] || $unassigned['deals']->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">Bez oblasti</x-slot>
            <x-slot name="description">Tohle nejde přiřadit vývoji ani marketingu, takže to zatím není v žádné faktuře. Doplňte oblast u hodin nebo u obchodu.</x-slot>

            @if ($unassigned['hours'])
                <ul class="space-y-1 text-sm">
                    @foreach ($unassigned['hours'] as $item)
                        <li>
                            <a href="{{ $item['url'] }}" class="font-medium text-gray-950 hover:text-primary-600 dark:text-white">{{ $item['name'] }}</a>
                            <span class="text-warning-600 dark:text-warning-400">· {{ $item['hours'] }} bez oblasti</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($unassigned['deals']->isNotEmpty())
                @include('filament.tools.pages.invoicing-deals', ['deals' => $unassigned['deals']])
            @endif
        </x-filament::section>
    @endif

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
