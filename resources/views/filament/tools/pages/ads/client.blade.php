<x-filament-panels::page>
    @php
        $status = $this->dataStatus();
        $refresh = $this->refreshState();
        $scopes = $this->scopes();
        $waiting = $this->waitingCampaigns();
        $groupOptions = $this->groupOptions();
        $events = $this->events();
        $changes = $this->recentChanges();
        $recovered = $this->recovered();
        $business = $this->business();
        $topAds = $this->topAds();
        $view = $this->performance();
        $targets = $view->targets();
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

    {{-- Čemu věřit: do kdy jsou data, kdy se stahovalo, jestli něco selhalo. --}}
    <div @class([
        'flex flex-wrap items-start justify-between gap-3 rounded-xl p-4 text-sm ring-1',
        'bg-white ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10' => $status->isFresh(),
        'bg-warning-50 ring-warning-200 dark:bg-warning-500/10 dark:ring-warning-500/30' => in_array($status->state, ['stale', 'waiting'], true),
        'bg-danger-50 ring-danger-200 dark:bg-danger-500/10 dark:ring-danger-500/30' => $status->state === 'error',
    ])>
        <div class="flex min-w-0 items-start gap-3">
            <x-filament::badge :color="$status->color()" icon="heroicon-m-circle-stack" class="shrink-0">{{ $status->badge() }}</x-filament::badge>
            <p class="text-gray-700 dark:text-gray-300">{{ $status->sentence() }}</p>
        </div>
        <p class="shrink-0 text-xs text-gray-500 dark:text-gray-400">
            @if ($refresh['pending'])
                Obnova čísel čeká, do 5 minut.
            @elseif ($refresh['next'])
                Další ruční obnova od {{ $refresh['next'] }}.
            @else
                Čísla jde obnovit v Další → Obnovit čísla.
            @endif
        </p>
    </div>

    @if ($scopes)
        <div class="flex flex-wrap items-center gap-2">
            @foreach ($scopes as $row)
                <button type="button" wire:click="setGroup('{{ $row['key'] }}')" @class([
                    'flex flex-col items-start rounded-lg px-3 py-2 text-left ring-1 transition',
                    'bg-primary-600 text-white ring-primary-600' => $row['active'],
                    'bg-white text-gray-700 ring-gray-950/10 hover:ring-gray-950/25 dark:bg-gray-900 dark:text-gray-300 dark:ring-white/10 dark:hover:ring-white/25' => ! $row['active'],
                ])>
                    <span class="text-sm font-semibold">{{ $row['label'] }}@if ($row['goal']) <span @class(['font-normal', 'text-white/80' => $row['active'], 'text-gray-500' => ! $row['active']])>· {{ $row['goal'] }}</span>@endif</span>
                    <span @class(['text-xs tabular-nums', 'text-white/80' => $row['active'], 'text-gray-500 dark:text-gray-400' => ! $row['active']])>{{ $row['spend'] }}</span>
                </button>
            @endforeach
            <div class="ml-auto flex gap-2">
                {{ $this->editGroupAction }}
                {{ $this->createGroupAction }}
            </div>
        </div>
    @else
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-dashed border-gray-300 p-4 text-sm text-gray-600 dark:border-white/15 dark:text-gray-400">
            <p>Míchá účet prodej, boosting nebo nábor? Rozdělte kampaně do skupin. Každá dostane vlastní cíl, rozpočet, čísla, upozornění i report.</p>
            {{ $this->createGroupAction }}
        </div>
    @endif

    @if ($waiting->isNotEmpty())
        <div class="rounded-xl bg-info-50 p-4 text-sm text-info-800 ring-1 ring-info-200 dark:bg-info-500/10 dark:text-info-300 dark:ring-info-500/30">
            <p class="font-semibold">{{ $waiting->count() === 1 ? 'Nová kampaň čeká' : 'Nové kampaně čekají' }} na zařazení do skupiny</p>
            <ul class="mt-2 space-y-1">
                @foreach ($waiting as $campaign)
                    <li class="flex flex-wrap items-center gap-2" wire:key="waiting-{{ $campaign->id }}">
                        <span class="min-w-0 truncate">{{ $campaign->name }}</span>
                        <select
                            class="rounded-md border-gray-300 py-0.5 pl-2 pr-7 text-xs text-gray-900 dark:border-white/10 dark:bg-gray-900 dark:text-white"
                            x-on:change="$wire.assignCampaign({{ $campaign->id }}, $event.target.value)"
                            aria-label="Skupina pro {{ $campaign->name }}"
                        >
                            <option value="">Zařadit do…</option>
                            @foreach ($groupOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                            <option value="none">Nechat mimo skupiny</option>
                        </select>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($alerts->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">Upozornění ({{ $alerts->count() }})</x-slot>

            <ul class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($alerts as $alert)
                    <li @class(['py-4 first:pt-0 last:pb-0', 'opacity-60' => $alert->isSnoozed()]) wire:key="alert-{{ $alert->id }}">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 max-w-3xl">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-filament::badge :color="$alert->severity->getColor()">{{ $alert->severity->getLabel() }}</x-filament::badge>
                                    <x-filament::badge :color="$alert->status->getColor()">{{ $alert->status->getLabel() }}</x-filament::badge>
                                    @if ($alert->category === 'data')
                                        <x-filament::badge color="gray">Data a měření</x-filament::badge>
                                    @endif
                                    <h3 class="font-semibold text-gray-950 dark:text-white">{{ $alert->title }}</h3>
                                </div>
                                @if ($alert->facts)
                                    <p class="mt-2 whitespace-pre-line text-sm text-gray-800 dark:text-gray-200"><span class="font-semibold">Co se změnilo:</span> {{ $alert->facts }}</p>
                                @endif
                                @if ($alert->basis)
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Z dat: {{ $alert->basis }}</p>
                                @endif
                                <p class="mt-2 whitespace-pre-line text-sm text-gray-600 dark:text-gray-400">@if ($alert->facts)<span class="font-semibold">Co prověřit:</span> @endif{{ $alert->recommendation }}</p>
                                <p class="mt-1 text-xs text-gray-400">
                                    Od {{ $alert->detected_on->format('j. n.') }}, naposledy {{ $alert->last_seen_on->format('j. n.') }}@if ($alert->occurrences > 1) · {{ $alert->occurrences }}. výskyt @endif
                                    @if ($alert->waiting_for) · čeká se na: {{ $alert->waiting_for }}@endif
                                    @if ($alert->isSnoozed()) · odloženo do {{ $alert->snoozed_until->format('j. n.') }}@endif
                                </p>
                            </div>

                            <div class="flex shrink-0 flex-wrap gap-2">
                                @if ($alert->status->value !== 'acknowledged')
                                    <x-filament::button size="xs" color="gray" wire:click="acknowledgeAlert({{ $alert->id }})">Řeším</x-filament::button>
                                @endif
                                @if ($alert->status->value !== 'waiting')
                                    {{ ($this->waitAlertAction)(['alert' => $alert->id]) }}
                                @endif
                                {{ ($this->snoozeAlertAction)(['alert' => $alert->id]) }}
                                <x-filament::button size="xs" color="success" wire:click="resolveAlert({{ $alert->id }})">Vyřešeno</x-filament::button>
                                <x-filament::button size="xs" color="gray" outlined wire:click="dismissAlert({{ $alert->id }})">Zamítnout</x-filament::button>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($recovered->isNotEmpty())
                <details class="mt-4 border-t border-gray-100 pt-3 text-sm dark:border-white/5">
                    <summary class="cursor-pointer text-gray-500 dark:text-gray-400">Vrátilo se do normálu za poslední týden ({{ $recovered->count() }})</summary>
                    <ul class="mt-2 space-y-1 text-gray-600 dark:text-gray-400">
                        @foreach ($recovered as $alert)
                            <li>{{ $alert->recovered_on->format('j. n.') }} · {{ $alert->title }}</li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </x-filament::section>
    @elseif ($recovered->isNotEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">Nic nesvítí. Za poslední týden se vrátilo do normálu: {{ $recovered->pluck('title')->implode(', ') }}.</p>
    @endif

    @if ($view->dataNote())
        <p class="rounded-lg bg-warning-50 px-4 py-3 text-sm text-warning-800 ring-1 ring-warning-200 dark:bg-warning-500/10 dark:text-warning-300 dark:ring-warning-500/30">{{ $view->dataNote() }}</p>
    @endif

    @if (! $view->hasData())
        <x-filament::section>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                @if ($status->covers($view->period))
                    Data za tohle období jsou stažená a útrata je nulová: {{ $view->scopeLabel() ? 'kampaně skupiny' : 'kampaně' }} v něm neběžely.
                @elseif ($status->state === 'waiting')
                    Účet se ještě nestahoval. Čísla i historie se stáhnou ráno v 6:00, nebo hned po Další → Obnovit čísla.
                @else
                    Za tohle období čísla chybí, není to nula. Zkontrolujte stav dat nahoře a stav účtů níž.
                @endif
            </p>
        </x-filament::section>
    @else
        @if ($targets)
            <div class="flex flex-wrap gap-3">
                @foreach ($targets as $target)
                    <div @class([
                        'flex items-center gap-2 rounded-lg px-3 py-2 text-sm ring-1',
                        'bg-success-50 text-success-800 ring-success-200 dark:bg-success-500/10 dark:text-success-300 dark:ring-success-500/30' => $target['ok'] === true,
                        'bg-danger-50 text-danger-800 ring-danger-200 dark:bg-danger-500/10 dark:text-danger-300 dark:ring-danger-500/30' => $target['ok'] === false,
                        'bg-gray-50 text-gray-700 ring-gray-200 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10' => $target['ok'] === null,
                    ])>
                        <x-filament::icon :icon="$target['ok'] === true ? 'heroicon-m-check-circle' : ($target['ok'] === false ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-minus-circle')" class="size-4" />
                        <span>{{ $target['label'] }} <span class="font-semibold tabular-nums">{{ $target['value'] }}</span>, cíl {{ $target['target'] }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            @foreach ($tiles as $tile)
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10" @if ($tile['description']) title="{{ $tile['description'] }}" @endif>
                    <p class="flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400">
                        <span class="min-w-0 truncate">{{ $tile['label'] }}</span>
                        @if ($tile['description'])
                            <x-filament::icon icon="heroicon-m-information-circle" class="size-3.5 shrink-0 opacity-50" />
                        @endif
                    </p>
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
                <x-ads.chart :labels="$chart['labels']" :bars="$chart['bars']" :line="$chart['line']" :states="$chart['states']" :markers="$chart['markers']" class="text-gray-600 dark:text-gray-300" />
            </x-filament::section>
            @endif

            @if ($view->shows('funnel'))
            <x-filament::section @class(['xl:col-span-3' => ! $view->shows('chart')])>
                <x-slot name="heading">Události po cestě {{ $view->goal->value === 'purchases' ? 'k nákupu' : 'ke konverzi' }}</x-slot>

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
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">poměr k předchozímu kroku {{ $step['rate'] }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                    <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">Meta počítá události, ne lidi. Poměr neříká, že titíž lidé prošli všemi kroky: nákup může přijít bez zaznamenaného košíku a jeden člověk může košík otevřít několikrát.</p>
                    <p class="mt-1 text-xs text-gray-400">Pruhy jsou v logaritmickém měřítku, jinak by konverze proti zobrazením nebyly vidět.</p>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">Reklamní systém neposílá kroky mezi proklikem a konverzí. Pixel nejspíš neměří košík a pokladnu.</p>
                @endif
            </x-filament::section>
            @endif
        </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">Termíny</x-slot>
                <x-slot name="afterHeader">{{ $this->addEventAction }}</x-slot>

                @if ($events->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400">Žádný nevyřešený termín na 60 dní dopředu. Konce licencí, akce a čekání na podklady přidáte tlačítkem vpravo.</p>
                @else
                    <ul class="divide-y divide-gray-100 text-sm dark:divide-white/5">
                        @foreach ($events as $event)
                            <li class="flex flex-wrap items-start justify-between gap-2 py-2.5 first:pt-0" wire:key="event-{{ $event->id }}">
                                <div class="min-w-0">
                                    <p class="flex flex-wrap items-center gap-2">
                                        <x-filament::badge :color="$event->type->getColor()" size="sm">{{ $event->type->getLabel() }}</x-filament::badge>
                                        <span class="font-medium text-gray-950 dark:text-white">{{ $event->title }}</span>
                                    </p>
                                    <p @class(['mt-0.5 text-xs', 'text-danger-600 dark:text-danger-400' => $event->isOverdue(), 'text-gray-500 dark:text-gray-400' => ! $event->isOverdue()])>
                                        {{ $event->due_on->format('j. n. Y') }} ({{ $event->whenLabel() }})@if ($event->scopeLabel()) · {{ $event->scopeLabel() }}@endif
                                    </p>
                                    @if ($event->terms_url || $event->links)
                                        <p class="mt-0.5 flex flex-wrap gap-x-3 text-xs">
                                            @if ($event->terms_url)
                                                <a href="{{ $event->terms_url }}" target="_blank" rel="noopener" class="text-primary-600 hover:underline">Podmínky licence</a>
                                            @endif
                                            @foreach ($event->links ?? [] as $link)
                                                <a href="{{ $link['url'] }}" target="_blank" rel="noopener" class="text-primary-600 hover:underline">{{ $link['label'] }}</a>
                                            @endforeach
                                        </p>
                                    @endif
                                </div>
                                <div class="flex shrink-0 gap-2">
                                    {{ ($this->editEventAction)(['event' => $event->id]) }}
                                    <x-filament::button size="xs" color="success" wire:click="resolveEvent({{ $event->id }})">Vyřešeno</x-filament::button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <a href="{{ $this->calendarUrl() }}" class="mt-3 inline-block text-sm font-medium text-primary-600 hover:underline">Všechny termíny</a>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Historie zásahů</x-slot>
                <x-slot name="afterHeader">{{ $this->logChangeAction }}</x-slot>

                @if ($changes->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400">Zatím nic. Zapisujte změny rozpočtu, nové kreativy, změny nabídky nebo webu: ukážou se v grafu a v reportu a agent z nich vychází, když hledá příčinu výkyvu.</p>
                @else
                    <ul class="divide-y divide-gray-100 text-sm dark:divide-white/5">
                        @foreach ($changes as $change)
                            <li class="py-2.5 first:pt-0">
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $change->changed_on->format('j. n. Y') }} · {{ $change->type->getLabel() }}@if ($change->scopeLabel()) · {{ $change->scopeLabel() }}@endif @if ($change->user) · {{ $change->user->name }}@endif</p>
                                <p class="text-gray-800 dark:text-gray-200">{{ $change->description }}</p>
                                @if ($change->expected)
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Čekáme: {{ $change->expected }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-filament::section>
        </div>

        @if ($topAds['rows'])
            <x-filament::section>
                <x-slot name="heading">Reklamy a kreativy</x-slot>
                <div class="-mt-1 mb-4 flex flex-wrap items-start justify-between gap-3">
                    <p class="max-w-3xl text-sm text-gray-500 dark:text-gray-400">{{ $topAds['period'] }}, reklamy s největší útratou. Kreativní testy (hypotéza, výsledek, poučení) se zapisují u klienta.</p>
                    <x-filament::link :href="$topAds['tests_url']" size="sm" icon="heroicon-m-beaker">Kreativní testy</x-filament::link>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-max text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-gray-950 dark:border-white/10 dark:text-white">
                                <th class="px-3 py-2 font-semibold">Reklama</th>
                                <th class="px-3 py-2 text-right font-semibold">Útrata</th>
                                <th class="px-3 py-2 text-right font-semibold">{{ $view->goal->conversionLabel() }}</th>
                                <th class="px-3 py-2 text-right font-semibold">{{ $view->goal->costLabel() }}</th>
                                @if ($view->goal->hasValue())
                                    <th class="px-3 py-2 text-right font-semibold">ROAS</th>
                                @endif
                                <th class="px-3 py-2 text-right font-semibold">CTR odkazu</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($topAds['rows'] as $ad)
                                <tr>
                                    <td class="max-w-md px-3 py-2">
                                        <p class="flex flex-wrap items-center gap-2">
                                            <span class="truncate font-medium text-gray-950 dark:text-white">{{ $ad['name'] }}</span>
                                            @if ($ad['tag'])
                                                <x-filament::badge size="sm" :color="$ad['tag'] === 'nejlevnější konverze' ? 'success' : 'danger'">{{ $ad['tag'] }}</x-filament::badge>
                                            @endif
                                        </p>
                                        <p class="truncate text-xs text-gray-500">{{ $ad['campaign'] }}</p>
                                    </td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $ad['spend'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $ad['conversions'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $ad['cost'] }}</td>
                                    @if ($view->goal->hasValue())
                                        <td class="px-3 py-2 text-right tabular-nums">{{ $ad['roas'] }}</td>
                                    @endif
                                    <td class="px-3 py-2 text-right tabular-nums">{{ $ad['ctr'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

        @if ($business['show_shop'])
            <x-filament::section>
                <x-slot name="heading">Skutečné tržby e-shopu</x-slot>
                <div class="-mt-1 mb-4 flex flex-wrap items-start justify-between gap-3">
                    <p class="max-w-3xl text-sm text-gray-500 dark:text-gray-400">Objednávky a tržby z administrace e-shopu proti útratě ({{ $business['shop_scope'] === 'Celý účet' ? 'celý účet' : 'skupina '.$business['shop_scope'] }}). Nákupy z reklam si připisuje každý systém sám, jako tržby se sčítat nedají.</p>
                    {{ $this->logShopResultAction }}
                </div>

                @if ($business['shop'])
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-max text-left text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 text-gray-950 dark:border-white/10 dark:text-white">
                                    <th class="px-3 py-2 font-semibold">Období</th>
                                    <th class="px-3 py-2 text-right font-semibold">Objednávky</th>
                                    <th class="px-3 py-2 text-right font-semibold">Tržby e-shopu</th>
                                    <th class="px-3 py-2 text-right font-semibold">Útrata za reklamy</th>
                                    <th class="px-3 py-2 text-right font-semibold" title="Náklady na obrat: útrata děleno tržbami e-shopu">PNO</th>
                                    <th class="px-3 py-2 text-right font-semibold" title="Tržby e-shopu na korunu reklamy (MER)">Tržby / útrata</th>
                                    <th class="px-3 py-2 font-semibold">Reklamy si připsaly</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                @foreach ($business['shop'] as $row)
                                    <tr>
                                        <td class="px-3 py-2">{{ $row['period'] }}@if ($row['source'])<span class="block text-xs text-gray-500">{{ $row['source'] }}</span>@endif</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ $row['orders'] }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ $row['revenue'] }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ $row['spend'] }}</td>
                                        <td class="px-3 py-2 text-right font-semibold tabular-nums">{{ $row['cos'] }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ $row['mer'] }}</td>
                                        <td class="px-3 py-2 text-xs text-gray-600 dark:text-gray-400">{{ $row['attributed'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">Zatím nic. Jednou měsíčně zapište objednávky a tržby z e-shopu, ať je vidět skutečný dopad reklam, ne jen to, co si připsala Meta.</p>
                @endif
            </x-filament::section>
        @endif

        @if ($business['show_leads'])
            <x-filament::section>
                <x-slot name="heading">Kvalita leadů a obchody</x-slot>
                <div class="-mt-1 mb-4 flex flex-wrap items-start justify-between gap-3">
                    <p class="max-w-3xl text-sm text-gray-500 dark:text-gray-400">Co se s leady stalo u klienta, přepočtené na útratu za stejné období ({{ $business['leads_scope'] === 'Celý účet' ? 'celý účet' : 'skupina '.$business['leads_scope'] }}). Levný formulář není úspěch, když z něj nic není.</p>
                    {{ $this->logLeadQualityAction }}
                </div>

                @if ($business['leads'])
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-max text-left text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 text-gray-950 dark:border-white/10 dark:text-white">
                                    <th class="px-3 py-2 font-semibold">Období</th>
                                    <th class="px-3 py-2 text-right font-semibold">Leady u klienta</th>
                                    <th class="px-3 py-2 text-right font-semibold" title="Leady, které si za stejné období připsaly reklamní systémy">Připsané reklamám</th>
                                    <th class="px-3 py-2 text-right font-semibold">Relevantní</th>
                                    <th class="px-3 py-2 text-right font-semibold">Obchody</th>
                                    <th class="px-3 py-2 text-right font-semibold">Hodnota</th>
                                    <th class="px-3 py-2 text-right font-semibold">Cena za relevantní</th>
                                    <th class="px-3 py-2 text-right font-semibold">Cena za obchod</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                @foreach ($business['leads'] as $row)
                                    <tr>
                                        <td class="px-3 py-2">{{ $row['period'] }}@if ($row['source'])<span class="block text-xs text-gray-500">{{ $row['source'] }}</span>@endif</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ $row['leads'] }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ $row['ad_leads'] }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ $row['qualified'] }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ $row['deals'] }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ $row['deal_value'] }}</td>
                                        <td class="px-3 py-2 text-right font-semibold tabular-nums">{{ $row['cost_qualified'] }}</td>
                                        <td class="px-3 py-2 text-right font-semibold tabular-nums">{{ $row['cost_deal'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">Zatím žádná zpětná vazba od klienta. Bez ní nevíme, jestli jsou leady k něčemu.</p>
                @endif
            </x-filament::section>
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
                            @if ($groupOptions)
                                <th class="px-3 py-2 font-semibold">Skupina</th>
                            @endif
                            <th class="px-3 py-2 text-right font-semibold">Útrata</th>
                            <th class="px-3 py-2 text-right font-semibold">{{ $view->goal->conversionLabel() }}</th>
                            <th class="px-3 py-2 text-right font-semibold">{{ $view->goal->costLabel() }}</th>
                            @if ($view->goal->hasValue())
                                <th class="px-3 py-2 text-right font-semibold">ROAS</th>
                            @endif
                            <th class="px-3 py-2 text-right font-semibold">CTR odkazu</th>
                            <th class="px-3 py-2 text-right font-semibold">Prokliky</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($campaigns as $campaign)
                            <tr wire:key="campaign-{{ $campaign['id'] }}">
                                <td class="max-w-md px-3 py-2">
                                    <p class="truncate font-medium text-gray-950 dark:text-white">{{ $campaign['name'] }}</p>
                                    <p class="text-xs text-gray-500">
                                        @if ($campaign['status'])
                                            <span @class(['text-success-600 dark:text-success-400' => $campaign['running']])>{{ $campaign['status'] }}</span>
                                        @endif
                                        @if (count($accounts) > 1) · {{ $campaign['account'] }}@endif
                                    </p>
                                </td>
                                @if ($groupOptions)
                                    <td class="px-3 py-2">
                                        <select
                                            class="w-full min-w-32 rounded-md border-gray-300 py-1 pl-2 pr-7 text-xs text-gray-900 dark:border-white/10 dark:bg-gray-900 dark:text-white"
                                            x-on:change="$wire.assignCampaign({{ $campaign['id'] }}, $event.target.value)"
                                            aria-label="Skupina kampaně {{ $campaign['name'] }}"
                                        >
                                            <option value="none" @selected($campaign['group'] === null)>Mimo skupiny</option>
                                            @foreach ($groupOptions as $id => $name)
                                                <option value="{{ $id }}" @selected($campaign['group'] === $name)>{{ $name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                @endif
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
            <x-slot name="heading">{{ $view->scopeLabel() ? 'Cíle skupiny '.$view->scopeLabel() : 'Domluva s klientem' }}</x-slot>

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
                    @if ($budget['note'])
                        <p class="mt-0.5 text-xs text-warning-600 dark:text-warning-400">{{ $budget['note'] }}</p>
                    @endif
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
                            {{ $account['active'] ? ($account['synced'] ? 'Staženo '.$account['synced'] : 'Stáhne se ráno v 6:00') : 'Vypnutý, nestahuje se' }}@if ($account['active'] && $account['through']) · data do {{ $account['through'] }}@endif
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
