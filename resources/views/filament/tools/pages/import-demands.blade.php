<x-filament-panels::page>
    <form wire:submit.prevent>
        {{ $this->form }}
    </form>

    @if ($this->preview)
        <x-filament::section>
            <x-slot name="heading">Náhled prvních {{ count($this->preview) }} řádků</x-slot>
            <x-slot name="description">Soubor obsahuje {{ $this->rowCount }} datových řádků.</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full min-w-max text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-white/10">
                            @foreach ($this->header as $column)
                                <th class="whitespace-nowrap px-3 py-2 font-semibold text-gray-950 dark:text-white">
                                    {{ $column }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->preview as $row)
                            <tr class="border-b border-gray-100 last:border-0 dark:border-white/5">
                                @foreach ($this->header as $index => $column)
                                    <td class="max-w-xs truncate px-3 py-2 text-gray-600 dark:text-gray-400">
                                        {{ $row[$index] ?? '' }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    @if ($this->summary)
        <x-filament::section icon="heroicon-o-check-circle" icon-color="success">
            <x-slot name="heading">Výsledek importu</x-slot>

            <dl class="grid gap-4 sm:grid-cols-3">
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Nových poptávek</dt>
                    <dd class="text-2xl font-semibold text-gray-950 dark:text-white">{{ $this->summary['created'] }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Aktualizovaných</dt>
                    <dd class="text-2xl font-semibold text-gray-950 dark:text-white">{{ $this->summary['updated'] }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Řádků bez odkazu</dt>
                    <dd class="text-2xl font-semibold text-gray-950 dark:text-white">{{ $this->summary['skipped'] }}</dd>
                </div>
            </dl>

            <div class="mt-6">
                <x-filament::button tag="a" :href="$this->demandsUrl()">
                    Otevřít poptávky
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif

    <x-filament::section collapsible collapsed>
        <x-slot name="heading">Jak má tabulka vypadat</x-slot>

        <p class="text-sm text-gray-600 dark:text-gray-400">
            Očekávaná hlavička:
            <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs dark:bg-white/10">Priorita,Zdroj,URL,Datum,Co chtějí,Odhad ceny,Stav,Datum reakce,Poznámka</code>
        </p>

        <ul class="mt-3 space-y-1.5 text-sm text-gray-600 dark:text-gray-400">
            <li><strong class="text-gray-950 dark:text-white">URL</strong> je jediný povinný sloupec a zároveň identita poptávky. Podle něj se pozná, jestli se řádek zakládá, nebo aktualizuje.</li>
            <li><strong class="text-gray-950 dark:text-white">Co chtějí</strong> se dělí na název a shrnutí v místě první pomlčky obklopené mezerami. Z „4horse.cz – přebíraný e-shop" bude název „4horse.cz". Rozsah v ceně („25–50k") zůstane celý.</li>
            <li><strong class="text-gray-950 dark:text-white">Zdroj</strong> rozumí názvům „Shoptet Partneři", „Webtrh", „Na volné noze", „Upgates". Neznámý portál spadne do „Jiné".</li>
            <li><strong class="text-gray-950 dark:text-white">Stav, Datum reakce a Poznámka</strong> se převezmou jen u nově zakládané poptávky. U té, kterou už vedeme, zůstane náš stav beze změny, i kdyby v tabulce bylo něco jiného.</li>
        </ul>
    </x-filament::section>
</x-filament-panels::page>
