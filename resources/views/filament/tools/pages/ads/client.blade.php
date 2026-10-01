<x-filament-panels::page>
    @php
        $view = $this->performance();
        $tiles = $view->tiles();
        $chart = $view->chart();
        $funnel = $view->funnel();
        $campaigns = $view->campaigns();
        $alerts = $this->alerts();
        $budget = $this->budget();
        $agreement = $this->agreement();
        $accounts = $this->accounts();
        $reports = $this->reports();
        $advice = $this->advice();
        $byAccount = $view->byAccount();
        $analytics = $view->analytics();
        $billing = $this->billing();
        $time = $this->recentTime();
    @endphp

    @include('filament.tools.pages.ads.partials.period', ['note' => $this->periodNote()])

    @if ($alerts->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">Upozornění ({{ $alerts->count() }})</x-slot>

            <ul class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($alerts as $alert)
                    <li class="py-4 first:pt-0 last:pb-0" wire:key="alert-{{ $alert->id }}">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 max-w-3xl">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-filament::badge :color="$alert->severity->getColor()">{{ $alert->severity->getLabel() }}</x-filament::badge>
                                    @if ($alert->status->value === 'acknowledged')
                                        <x-filament::badge color="gray">Řeší se</x-filament::badge>
                                    @endif
                                    <h3 class="font-semibold text-gray-950 dark:text-white">{{ $alert->title }}</h3>
                                </div>
                                <p class="mt-2 whitespace-pre-line text-sm text-gray-600 dark:text-gray-400">{{ $alert->recommendation }}</p>
                                <p class="mt-1 text-xs text-gray-400">Od {{ $alert->detected_on->format('j. n.') }}, naposledy {{ $alert->last_seen_on->format('j. n.') }}</p>
                            </div>

                            <div class="flex shrink-0 gap-2">
                                @if ($alert->status->value === 'open')
                                    <x-filament::button size="xs" color="gray" wire:click="acknowledgeAlert({{ $alert->id }})">Řeším</x-filament::button>
                                @endif
                                <x-filament::button size="xs" color="success" wire:click="resolveAlert({{ $alert->id }})">Vyřešeno</x-filament::button>
                                <x-filament::button size="xs" color="gray" outlined wire:click="dismissAlert({{ $alert->id }})">Zamítnout</x-filament::button>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif

    @if (! $view->hasData())
        <x-filament::section>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Za tohle období nejsou žádná čísla. Když jste účet právě propojili, historie se stahuje na pozadí, obnovte stránku za minutu.
                Jinak zkontrolujte stav účtů níž.
            </p>
        </x-filament::section>
    @else
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            @foreach ($tiles as $tile)
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $tile['label'] }}</p>
                    <p class="mt-1 text-2xl font-semibold tracking-tight tabular-nums text-gray-950 dark:text-white">{{ $tile['value'] }}</p>
                    <p class="mt-1 flex flex-wrap gap-x-2 text-xs tabular-nums">
                        @if ($tile['change'])
                            <span @class([
                                'font-semibold',
                                'text-success-600 dark:text-success-400' => $tile['tone'] === 'good',
                                'text-danger-600 dark:text-danger-400' => $tile['tone'] === 'bad',
                                'text-gray-500 dark:text-gray-400' => $tile['tone'] === 'neutral',
                            ])>{{ $tile['change'] }}</span>
                        @endif
                        <span class="text-gray-400">předtím {{ $tile['previous'] }}</span>
                        @if ($tile['hint'])
                            <span class="text-gray-500 dark:text-gray-400">· {{ $tile['hint'] }}</span>
                        @endif
                    </p>
                </div>
            @endforeach
        </div>

        @if ($view->shows('chart') || $view->shows('funnel'))
        <div class="grid gap-6 xl:grid-cols-3">
            @if ($view->shows('chart'))
            <x-filament::section @class(['xl:col-span-2' => $view->shows('funnel'), 'xl:col-span-3' => ! $view->shows('funnel')])>
                <x-slot name="heading">Po dnech</x-slot>
                <x-ads.chart :labels="$chart['labels']" :bars="$chart['bars']" :line="$chart['line']" class="text-gray-600 dark:text-gray-300" />
            </x-filament::section>
            @endif

            @if ($view->shows('funnel'))
            <x-filament::section @class(['xl:col-span-3' => ! $view->shows('chart')])>
                <x-slot name="heading">Cesta k {{ $view->goal->value === 'leads' ? 'poptávce' : 'nákupu' }}</x-slot>

                @if ($funnel)
                    <ol class="space-y-3">
                        @foreach ($funnel as $step)
                            <li>
                                <div class="flex items-baseline justify-between gap-2 text-sm">
                                    <span class="text-gray-700 dark:text-gray-300">{{ $step['label'] }}</span>
                                    <span class="font-semibold tabular-nums text-gray-950 dark:text-white">{{ $step['value'] }}</span>
                                </div>
                                <div class="mt-1 h-2 rounded-full bg-gray-100 dark:bg-white/10">
                                    <div class="h-2 rounded-full bg-primary-500" style="width: {{ $step['width'] }}%"></div>
                                </div>
                                @if ($step['rate'])
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $step['rate'] }} z předchozího kroku</p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                    <p class="mt-4 text-xs text-gray-400">Pruhy jsou v logaritmickém měřítku, jinak by konverze proti zobrazením nebyly vidět.</p>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">Reklamní systém neposílá kroky mezi proklikem a konverzí. Pixel nejspíš neměří košík a pokladnu.</p>
                @endif
            </x-filament::section>
            @endif
        </div>
        @endif

        @if ($byAccount && $view->shows('accounts'))
            <x-filament::section>
                <x-slot name="heading">Podle reklamních účtů</x-slot>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-max text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-gray-950 dark:border-white/10 dark:text-white">
                                <th class="px-3 py-2 font-semibold">Účet</th>
                                <th class="px-3 py-2 text-right font-semibold">Útrata</th>
                                <th class="px-3 py-2 font-semibold">Podíl</th>
                                <th class="px-3 py-2 text-right font-semibold">{{ $view->goal->conversionLabel() }}</th>
                                <th class="px-3 py-2 text-right font-semibold">{{ $view->goal->costLabel() }}</th>
                                @if ($view->goal->hasValue())
                                    <th class="px-3 py-2 text-right font-semibold">ROAS</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($byAccount as $row)
                                <tr>
                                    <td class="px-3 py-2">
                                        <span class="font-medium text-gray-950 dark:text-white">{{ $row['name'] }}</span>
                                        <span class="ml-1 text-xs text-gray-500">{{ $row['platform'] }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $row['spend'] }}</td>
                                    <td class="w-48 px-3 py-2">
                                        <div class="h-2 rounded-full bg-gray-100 dark:bg-white/10">
                                            <div class="h-2 rounded-full bg-primary-500" style="width: {{ $row['share'] }}%"></div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $row['conversions'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $row['cost'] }}</td>
                                    @if ($view->goal->hasValue())
                                        <td class="px-3 py-2 text-right tabular-nums">{{ $row['roas'] }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

        @if ($analytics && $view->shows('analytics'))
            <x-filament::section>
                <x-slot name="heading">Co naměřil web (GA4)</x-slot>
                <x-slot name="description">Všechny zdroje návštěv dohromady. Reklamní systémy si nákupy připisují samy a často dvakrát, GA4 počítá každý jednou.</x-slot>

                <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                    @foreach ($analytics['tiles'] as $tile)
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $tile['label'] }}</p>
                            <p class="text-xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ $tile['value'] }}</p>
                            @if ($tile['change'])
                                <p @class([
                                    'text-xs tabular-nums',
                                    'text-success-600 dark:text-success-400' => $tile['tone'] === 'good',
                                    'text-danger-600 dark:text-danger-400' => $tile['tone'] === 'bad',
                                    'text-gray-500 dark:text-gray-400' => $tile['tone'] === 'neutral',
                                ])>{{ $tile['change'] }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if ($analytics['comparison'])
                    <p class="mt-4 rounded-lg bg-gray-50 p-3 text-sm text-gray-700 dark:bg-white/5 dark:text-gray-300">{{ $analytics['comparison'] }}</p>
                @endif

                @if ($analytics['channels'])
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full min-w-max text-left text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 text-gray-950 dark:border-white/10 dark:text-white">
                                    <th class="px-3 py-2 font-semibold">Kanál</th>
                                    <th class="px-3 py-2 text-right font-semibold">Návštěvy</th>
                                    <th class="px-3 py-2 text-right font-semibold">Podíl</th>
                                    <th class="px-3 py-2 text-right font-semibold">{{ $view->goal->value === 'purchases' ? 'Nákupy' : 'Klíčové události' }}</th>
                                    @if ($view->goal->value === 'purchases')
                                        <th class="px-3 py-2 text-right font-semibold">Tržby</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                @foreach ($analytics['channels'] as $channel)
                                    <tr>
                                        <td class="px-3 py-2 text-gray-950 dark:text-white">{{ $channel['channel'] }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ $channel['sessions'] }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ $channel['share'] }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ $channel['purchases'] }}</td>
                                        @if ($view->goal->value === 'purchases')
                                            <td class="px-3 py-2 text-right tabular-nums">{{ $channel['revenue'] }}</td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>
        @endif

        @if ($advice)
            <x-filament::section>
                <x-slot name="heading">Návrh úprav od Clauda</x-slot>
                <x-slot name="description">{{ $this->adviceDate() }}. Vychází jen z čísel, kontext kampaní zná Pavel.</x-slot>
                <div class="ads-advice text-sm text-gray-700 dark:text-gray-300">{{ $advice }}</div>
            </x-filament::section>
        @endif

        @if ($view->shows('campaigns'))
        <x-filament::section>
            <x-slot name="heading">Kampaně ({{ count($campaigns) }})</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full min-w-max text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-950 dark:border-white/10 dark:text-white">
                            <th class="px-3 py-2 font-semibold">Kampaň</th>
                            <th class="px-3 py-2 text-right font-semibold">Útrata</th>
                            <th class="px-3 py-2 text-right font-semibold">{{ $view->goal->conversionLabel() }}</th>
                            <th class="px-3 py-2 text-right font-semibold">{{ $view->goal->costLabel() }}</th>
                            @if ($view->goal->hasValue())
                                <th class="px-3 py-2 text-right font-semibold">ROAS</th>
                            @endif
                            <th class="px-3 py-2 text-right font-semibold">CTR</th>
                            <th class="px-3 py-2 text-right font-semibold">Prokliky</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($campaigns as $campaign)
                            <tr>
                                <td class="max-w-md px-3 py-2">
                                    <p class="truncate font-medium text-gray-950 dark:text-white">{{ $campaign['name'] }}</p>
                                    @if (count($accounts) > 1)
                                        <p class="text-xs text-gray-500">{{ $campaign['account'] }}</p>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">
                                    {{ $campaign['spend'] }}
                                    @if ($campaign['spend_change'])
                                        <span class="block text-xs text-gray-500">{{ $campaign['spend_change'] }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $campaign['conversions'] }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $campaign['cost'] }}</td>
                                @if ($view->goal->hasValue())
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $campaign['roas'] }}</td>
                                @endif
                                <td class="px-3 py-2 text-right tabular-nums">{{ $campaign['ctr'] }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ $campaign['clicks'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
        @endif
    @endif

    <div class="grid gap-6 lg:grid-cols-2 2xl:grid-cols-4">
        <x-filament::section>
            <x-slot name="heading">Domluva s klientem</x-slot>

            @if ($budget)
                <div class="mb-4">
                    <div class="flex items-baseline justify-between gap-2 text-sm">
                        <span class="text-gray-500 dark:text-gray-400">Rozpočet v měsíci</span>
                        <span class="font-semibold text-gray-950 dark:text-white">{{ $budget['label'] }}</span>
                    </div>
                    <div class="relative mt-1.5 h-2 rounded-full bg-gray-100 dark:bg-white/10">
                        <div class="absolute inset-y-0 left-0 rounded-full bg-primary-500" style="width: {{ $budget['percent'] }}%"></div>
                        <div class="absolute -inset-y-0.5 w-0.5 rounded-full bg-gray-950 dark:bg-white" style="left: {{ $budget['expected'] }}%" title="Plán k dnešku"></div>
                    </div>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ collect([$budget['pace'], $budget['projected']])->filter()->implode(' · ') }}</p>
                </div>
            @endif

            @if ($agreement)
                <dl class="space-y-2 text-sm">
                    @foreach ($agreement as $row)
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500 dark:text-gray-400">{{ $row['label'] }}</dt>
                            <dd class="text-right font-medium text-gray-950 dark:text-white">{{ $row['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">Cíle ani rozpočet zatím nejsou. Doplňte je tlačítkem „Cíle a paušál“.</p>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Reklamní účty</x-slot>

            <ul class="space-y-3 text-sm">
                @foreach ($accounts as $account)
                    <li @class(['opacity-50' => ! $account['active']])>
                        <div class="flex items-center justify-between gap-2">
                            @if ($account['manager_url'])
                                <a href="{{ $account['manager_url'] }}" target="_blank" rel="noopener" class="truncate font-medium text-gray-950 hover:text-primary-600 dark:text-white">{{ $account['name'] }}</a>
                            @else
                                <span class="truncate font-medium text-gray-950 dark:text-white">{{ $account['name'] }}</span>
                            @endif
                            <x-filament::badge class="shrink-0" :color="$account['ok'] ? 'success' : 'danger'">{{ $account['error'] ? 'Chyba' : $account['status'] }}</x-filament::badge>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $account['platform'] }} ·
                            {{ $account['active'] ? ($account['synced'] ? 'Staženo '.$account['synced'] : 'Zatím nestaženo') : 'Vypnutý, nestahuje se' }}
                        </p>
                        @if ($account['error'])
                            <p class="mt-1 text-xs text-danger-600 dark:text-danger-400">{{ $account['error'] }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Reporty</x-slot>

            @if ($reports->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">Zatím žádný. Koncept týdenního reportu vzniká každé pondělí ráno, nebo ho připravte tlačítkem nahoře.</p>
            @else
                <ul class="space-y-2 text-sm">
                    @foreach ($reports as $report)
                        <li class="flex items-center justify-between gap-2">
                            <a href="{{ $this->reportUrl($report->id) }}" class="truncate text-gray-950 hover:text-primary-600 dark:text-white">{{ $report->title }}</a>
                            <x-filament::badge :color="$report->status->getColor()">{{ $report->status->getLabel() }}</x-filament::badge>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Hodiny tento měsíc</x-slot>

            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500 dark:text-gray-400">Odpracováno</dt>
                    <dd class="text-right font-medium text-gray-950 dark:text-white">{{ $billing->formatted()['hours'] }}</dd>
                </div>
                @if ($billing->extraHours() > 0)
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500 dark:text-gray-400">Navíc k fakturaci</dt>
                        <dd class="text-right font-medium text-gray-950 dark:text-white">{{ $billing->formatted()['extra'] }} · {{ $billing->formatted()['extra_amount'] }}</dd>
                    </div>
                @elseif ($billing->includedHours > 0)
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500 dark:text-gray-400">Zbývá v paušálu</dt>
                        <dd class="text-right font-medium text-gray-950 dark:text-white">{{ $billing->formatted()['remaining'] }}</dd>
                    </div>
                @endif
            </dl>

            @if ($time->isNotEmpty())
                <ul class="mt-4 space-y-2 border-t border-gray-100 pt-3 text-sm dark:border-white/5">
                    @foreach ($time as $entry)
                        <li class="flex justify-between gap-3">
                            <span class="min-w-0 truncate text-gray-700 dark:text-gray-300">{{ $entry->worked_on->format('j. n.') }} · {{ $entry->description }}</span>
                            <span class="shrink-0 tabular-nums text-gray-500">{{ $entry->duration() }}</span>
                        </li>
                    @endforeach
                </ul>
                <a href="{{ $this->timeEntriesUrl() }}" class="mt-3 inline-block text-sm font-medium text-primary-600 hover:underline">Všechny hodiny</a>
            @else
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">Tento měsíc zatím nic. Čas se zapisuje tlačítkem „Zapsat čas“.</p>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
