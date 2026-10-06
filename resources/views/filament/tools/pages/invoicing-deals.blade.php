{{-- Tabulka vyhraných zakázek ve Fakturaci, u oblasti i v „Bez oblasti“. --}}
<div class="mt-2 overflow-x-auto">
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
