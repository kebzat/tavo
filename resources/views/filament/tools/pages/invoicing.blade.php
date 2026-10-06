<x-filament-panels::page>
    <div class="flex flex-wrap items-center gap-2">
        <x-filament::button size="sm" color="gray" icon="heroicon-o-chevron-left" wire:click="previousMonth">Předchozí</x-filament::button>
        <x-filament::button size="sm" color="gray" icon="heroicon-o-chevron-right" icon-position="after" wire:click="nextMonth">Další</x-filament::button>
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

    <x-filament::section>
        <x-slot name="heading">Paušály a hodiny</x-slot>
        <x-slot name="description">Měsíční paušál z karty klienta (Pravidelná spolupráce) plus hodiny nad rámec paušálu sazbou z nastavení reklam. Nefakturovatelná práce se nepočítá.</x-slot>

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
                            <th class="px-3 py-2 font-semibold">Faktura</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($rows as $row)
                            <tr wire:key="billing-{{ $row['id'] }}">
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
                                            <x-filament::link tag="button" size="xs" color="gray" wire:click="unmarkInvoiced({{ $row['id'] }})" wire:confirm="Vrátit klienta mezi nevyfakturované?">Zpět</x-filament::link>
                                        </div>
                                        @if ($row['changed'])
                                            <div class="mt-1 flex items-center gap-2 text-xs text-warning-600 dark:text-warning-400">
                                                Od faktury přibyly hodiny nebo se změnil paušál.
                                                <x-filament::link tag="button" size="xs" wire:click="markInvoiced({{ $row['id'] }})">Vyfakturováno i to</x-filament::link>
                                            </div>
                                        @endif
                                    @else
                                        <x-filament::button size="xs" color="gray" icon="heroicon-m-check" wire:click="markInvoiced({{ $row['id'] }})">
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

    <x-filament::section>
        <x-slot name="heading">Jednorázové zakázky</x-slot>
        <x-slot name="description">Vyhrané obchody z CRM kromě průběžné správy. Nevyfakturované tu visí, dokud je neoznačíte, bez ohledu na měsíc.</x-slot>

        @if ($deals->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">Žádná vyhraná zakázka nečeká na fakturu.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-max text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-950 dark:border-white/10 dark:text-white">
                            <th class="px-3 py-2 font-semibold">Firma</th>
                            <th class="px-3 py-2 font-semibold">Zakázka</th>
                            <th class="px-3 py-2 font-semibold">Vyhráno</th>
                            <th class="px-3 py-2 text-right font-semibold">Částka</th>
                            <th class="px-3 py-2 font-semibold">Faktura</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($deals as $deal)
                            <tr wire:key="deal-{{ $deal['id'] }}">
                                <td class="px-3 py-2 font-medium text-gray-950 dark:text-white">{{ $deal['company'] }}</td>
                                <td class="px-3 py-2">
                                    <a href="{{ $deal['url'] }}" class="text-gray-950 hover:text-primary-600 dark:text-white">{{ $deal['title'] }}</a>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $deal['package'] }}</span>
                                </td>
                                <td class="px-3 py-2 tabular-nums">{{ $deal['won'] }}</td>
                                <td class="px-3 py-2 text-right font-semibold tabular-nums text-gray-950 dark:text-white">{{ $deal['value'] }}</td>
                                <td class="px-3 py-2">
                                    @if ($deal['invoiced'])
                                        <div class="flex items-center gap-2">
                                            <x-filament::badge color="success" icon="heroicon-m-check">{{ $deal['invoiced_note'] }}</x-filament::badge>
                                            <x-filament::link tag="button" size="xs" color="gray" wire:click="unmarkDealInvoiced({{ $deal['id'] }})" wire:confirm="Vrátit zakázku mezi nevyfakturované?">Zpět</x-filament::link>
                                        </div>
                                    @else
                                        <x-filament::button size="xs" color="gray" icon="heroicon-m-check" wire:click="markDealInvoiced({{ $deal['id'] }})">
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
