<x-filament-panels::page>
    <form wire:submit.prevent>
        {{ $this->form }}
    </form>

    @if ($this->preview)
        <x-filament::section>
            <x-slot name="heading">Náhled prvních {{ count($this->preview) }} řádků</x-slot>
            <x-slot name="description">Soubor obsahuje {{ $this->rowCount }} datových řádků.</x-slot>

            {{-- Tabulka rešerše bývá široká, proto se posouvá uvnitř sekce. --}}
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
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Založeno firem</dt>
                    <dd class="text-2xl font-semibold text-gray-950 dark:text-white">{{ $this->summary['created'] }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Přeskočeno jako duplicita</dt>
                    <dd class="text-2xl font-semibold text-gray-950 dark:text-white">{{ $this->summary['skipped'] }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Řádků bez názvu firmy</dt>
                    <dd class="text-2xl font-semibold text-gray-950 dark:text-white">{{ $this->summary['invalid'] }}</dd>
                </div>
            </dl>

            @if ($this->summary['duplicates'])
                <div class="mt-6">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Přeskočené firmy</h3>
                    <ul class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-400">
                        @foreach ($this->summary['duplicates'] as $duplicate)
                            <li>{{ $duplicate }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-6">
                <x-filament::button tag="a" :href="$this->companiesUrl()">
                    Otevřít seznam firem
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif

    <x-filament::section collapsible collapsed>
        <x-slot name="heading">Jak má tabulka vypadat</x-slot>

        <p class="text-sm text-gray-600 dark:text-gray-400">
            Očekávaná hlavička:
            <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs dark:bg-white/10">segment,firma,mesto,obor,web,platforma,bolest,balicek,reference,kontakt,priorita</code>
        </p>

        <ul class="mt-3 space-y-1.5 text-sm text-gray-600 dark:text-gray-400">
            <li><strong class="text-gray-950 dark:text-white">firma</strong> — jediný povinný sloupec. Řádek bez názvu se přeskočí.</li>
            <li><strong class="text-gray-950 dark:text-white">segment</strong> — „Lokální firma", „Zubní / zdraví", „SVJ / správa", „Konference", „E-shop", „Agentura". Neznámý text spadne do „Jiné".</li>
            <li><strong class="text-gray-950 dark:text-white">kontakt</strong> — volný text, např. <em>info@firma.cz, +420 777 123 456 (Jan Novák)</em>. Vytvoří se z něj hlavní kontakt; jméno se bere jen ze závorky.</li>
            <li><strong class="text-gray-950 dark:text-white">web</strong> — podle domény se poznávají duplicity. Firma s doménou, kterou už vedeme, se nenaimportuje.</li>
        </ul>
    </x-filament::section>
</x-filament-panels::page>
