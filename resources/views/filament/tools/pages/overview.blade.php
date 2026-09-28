<x-filament-panels::page>
    @php
        $kpi = $this->kpi();
        $rows = $this->rows();
        $chart = $this->chart();
        $chartMax = $this->chartMax();
        $log = $this->outreachLog();
        $money = $this->money();
        $moneyMonths = $this->moneyMonths();
        $moneyStages = $this->moneyStages();
    @endphp

    <x-filament::section>
        <x-slot name="heading">Peníze v obchodech</x-slot>
        <x-slot name="description">Jisté je to, co je vyhrané. Pravděpodobné je částka obchodu krát šance podle fáze. Potenciál je součet všeho rozjednaného, kdyby vyšlo úplně všechno.</x-slot>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border-l-4 border-success-500 bg-success-50 p-5 dark:bg-success-500/10">
                <p class="text-sm font-medium text-success-700 dark:text-success-400">Jisté, vyhráno tento měsíc</p>
                <p class="mt-1 text-3xl font-bold tracking-tight text-gray-950 dark:text-white">{{ $money['won_month'] }}</p>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Letos celkem {{ $money['won_year'] }}</p>
            </div>

            <div class="rounded-xl border-l-4 border-primary-500 bg-primary-50 p-5 dark:bg-primary-500/10">
                <p class="text-sm font-medium text-primary-700 dark:text-primary-400">Pravděpodobně přijde</p>
                <p class="mt-1 text-3xl font-bold tracking-tight text-gray-950 dark:text-white">{{ $money['weighted'] }}</p>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Z rozjednaných obchodů: {{ $money['open_count'] }}</p>
            </div>

            <div class="rounded-xl border-l-4 border-gray-300 bg-gray-50 p-5 dark:border-white/20 dark:bg-white/5">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Když vyjde všechno</p>
                <p class="mt-1 text-3xl font-bold tracking-tight text-gray-950 dark:text-white">{{ $money['potential'] }}</p>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Součet všech rozjednaných obchodů</p>
            </div>
        </div>

        @if ($money['has_deals'])
            <div class="mt-8 grid gap-10 lg:grid-cols-5">
                <div class="lg:col-span-3">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Po měsících</h3>

                    <div class="mt-4 flex items-end gap-2 sm:gap-3">
                        @foreach ($moneyMonths as $month)
                            <div class="flex min-w-0 flex-1 flex-col items-center gap-1.5">
                                <span class="hidden h-4 whitespace-nowrap text-xs font-semibold text-gray-950 sm:block dark:text-white">{{ $month['top'] }}</span>

                                {{-- Výšky jsou poměr k nejvyššímu sloupci, proto inline styl. --}}
                                <div
                                    class="flex h-48 w-full max-w-14 flex-col justify-end overflow-hidden rounded-t-md"
                                    title="Vyhráno {{ $month['won'] }} · pravděpodobně {{ $month['weighted'] }} · potenciál {{ $month['potential'] }}"
                                >
                                    <div class="w-full bg-primary-200 dark:bg-primary-500/30" style="height: {{ $month['rest_height'] }}%"></div>
                                    <div class="w-full bg-primary-500" style="height: {{ $month['weighted_height'] }}%"></div>
                                    <div class="w-full bg-success-500" style="height: {{ $month['won_height'] }}%"></div>
                                </div>

                                <span @class([
                                    'text-xs',
                                    'font-semibold text-gray-950 dark:text-white' => $month['current'],
                                    'text-gray-500 dark:text-gray-400' => ! $month['current'],
                                ])>{{ $month['label'] }}</span>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 flex flex-wrap gap-4 text-xs text-gray-600 dark:text-gray-400">
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-success-500"></span> Vyhráno</span>
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-primary-500"></span> Pravděpodobně přijde</span>
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-primary-200 dark:bg-primary-500/30"></span> Zbytek potenciálu</span>
                    </div>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Číslo nad sloupcem je vyhráno plus pravděpodobně. Rozjednané obchody padají do měsíce podle očekávaného data uzavření, bez data do tohoto měsíce.</p>
                </div>

                <div class="lg:col-span-2">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Rozjednané podle fáze</h3>

                    <ul class="mt-4 space-y-4">
                        @foreach ($moneyStages as $stage)
                            <li>
                                <div class="flex items-baseline justify-between gap-3 text-sm">
                                    <span class="text-gray-950 dark:text-white">
                                        {{ $stage['label'] }}
                                        <span class="text-gray-500 dark:text-gray-400">· {{ $stage['count'] }} · šance {{ $stage['probability'] }} %</span>
                                    </span>
                                    <span class="shrink-0 font-semibold text-gray-950 dark:text-white">{{ $stage['weighted'] }}</span>
                                </div>

                                <div class="relative mt-1.5 h-2.5 rounded-full bg-gray-100 dark:bg-white/5" title="Celkem {{ $stage['total'] }}, pravděpodobně {{ $stage['weighted'] }}">
                                    <div class="absolute inset-y-0 left-0 rounded-full bg-primary-200 dark:bg-primary-500/30" style="width: {{ $stage['total_width'] }}%"></div>
                                    <div class="absolute inset-y-0 left-0 rounded-full bg-primary-500" style="width: {{ $stage['weighted_width'] }}%"></div>
                                </div>

                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">celkem {{ $stage['total'] }}</p>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @else
            <p class="mt-6 text-sm text-gray-500 dark:text-gray-400">
                Zatím tu není žádný obchod. Když u firmy založíte obchod s částkou a fází, ukáže se tady, kolik z něj nejspíš přijde.
            </p>
        @endif
    </x-filament::section>

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
