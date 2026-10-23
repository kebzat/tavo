{{-- Ranní přehled: priority přihlášeného nahoře, pod tím přehledy od agenta po klientech. --}}
<x-filament-panels::page>
    @php($date = $this->date())

    <div class="flex items-center justify-between gap-3">
        <x-filament::button size="sm" color="gray" icon="heroicon-m-chevron-left" wire:click="shiftDay(-1)">Předchozí den</x-filament::button>
        <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $date->isToday() ? 'Dnes, ' : '' }}{{ $date->translatedFormat('l j. n. Y') }}</p>
        <x-filament::button size="sm" color="gray" icon="heroicon-m-chevron-right" icon-position="after" wire:click="shiftDay(1)" :disabled="$date->isToday()">Další den</x-filament::button>
    </div>

    @if ($date->isToday() && ($summary = $this->adsSummary()))
        {{-- Reklamy: problémy s daty, odchylky, termíny, reporty. Stejné jako nahoře v přehledu klientů. --}}
        <div class="flex flex-wrap items-center gap-2 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <span class="mr-1 text-sm font-semibold text-gray-950 dark:text-white">Reklamy dnes ráno:</span>
            @foreach ($summary as $item)
                @if ($item['url'])
                    <a href="{{ $item['url'] }}" class="contents"><x-filament::badge :color="$item['color']" size="lg">{{ $item['count'] }} {{ $item['label'] }}</x-filament::badge></a>
                @else
                    <x-filament::badge :color="$item['color']" size="lg">{{ $item['count'] }} {{ $item['label'] }}</x-filament::badge>
                @endif
            @endforeach
        </div>
    @endif

    @if ($date->isToday() && ($events = $this->adEvents())->isNotEmpty())
        <x-filament::section icon="heroicon-o-calendar-days">
            <x-slot name="heading">Termíny v reklamách</x-slot>
            <x-slot name="description">Visí tady, dokud je někdo neoznačí jako vyřešené.</x-slot>
            <ul class="divide-y divide-gray-100 text-sm dark:divide-white/5">
                @foreach ($events as $event)
                    <li class="flex flex-wrap items-baseline justify-between gap-2 py-2 first:pt-0">
                        <span class="min-w-0"><span class="font-medium text-gray-950 dark:text-white">{{ $event->client->name }}:</span> {{ $event->title }} <span class="text-gray-500">· {{ $event->type->todo() }}</span></span>
                        <span @class(['shrink-0 text-xs', 'text-danger-600 dark:text-danger-400' => $event->isOverdue(), 'text-gray-500' => ! $event->isOverdue()])>{{ $event->due_on->format('j. n.') }} ({{ $event->whenLabel() }})</span>
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif

    @if ($date->isToday())
        <div class="grid gap-4 lg:grid-cols-2">
            <x-filament::section icon="heroicon-o-flag">
                <x-slot name="heading">Moje priority</x-slot>
                <x-slot name="description">Po termínu, s termínem do dvou dnů, nebo s vysokou prioritou.</x-slot>

                <div class="space-y-2">
                    @forelse ($this->myTasks() as $row)
                        <a href="{{ $row['url'] }}" class="flex items-start justify-between gap-3 rounded-lg px-3 py-2 ring-1 ring-gray-950/5 hover:bg-gray-50 dark:ring-white/10 dark:hover:bg-white/5">
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $row['task']->title }}</span>
                                <span class="block truncate text-xs text-gray-500 dark:text-gray-400">
                                    {{ $row['task']->client->name }}@if ($row['task']->waiting_for) · čeká se na: {{ $row['task']->waiting_for }}@endif
                                </span>
                            </span>
                            <span @class([
                                'shrink-0 text-xs font-semibold',
                                'text-danger-600' => $row['overdue'],
                                'text-gray-500' => ! $row['overdue'],
                            ])>{{ $row['task']->due_on ? ($row['overdue'] ? 'po termínu ' : '').$row['task']->due_on->format('j. n.') : 'priorita' }}</span>
                        </a>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">Nic nehoří. Úkoly s termínem a prioritou se ukážou tady.</p>
                    @endforelse
                </div>
            </x-filament::section>

            <x-filament::section icon="heroicon-o-inbox-arrow-down">
                <x-slot name="heading">Návrhy úkolů ke schválení</x-slot>
                <x-slot name="description">Agent je připravil ze schůzek a e-mailů. Klient je nevidí, dokud je neschválíte.</x-slot>

                <div class="space-y-2">
                    @forelse ($this->proposals() as $row)
                        <a href="{{ $row['url'] }}" class="flex items-center justify-between rounded-lg px-3 py-2 ring-1 ring-gray-950/5 hover:bg-gray-50 dark:ring-white/10 dark:hover:bg-white/5">
                            <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $row['client']->name }}</span>
                            <x-filament::badge color="warning">{{ $row['count'] }}</x-filament::badge>
                        </a>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">Žádné návrhy nečekají.</p>
                    @endforelse
                </div>
            </x-filament::section>
        </div>
    @endif

    @forelse ($this->briefings() as $row)
        <x-filament::section :icon="$row['alerts'] > 0 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle'" :icon-color="$row['alerts'] > 0 ? 'warning' : 'success'">
            <x-slot name="heading">{{ $row['client']->name }}</x-slot>
            <x-slot name="description">
                {{ $row['alerts'] > 0 ? 'Živá upozornění: '.$row['alerts'] : 'Bez upozornění' }}@if ($row['proposals'] > 0) · návrhy úkolů: {{ $row['proposals'] }}@endif
            </x-slot>
            <x-slot name="afterHeader">
                <div class="flex gap-2">
                    @if ($row['ads_url'])
                        <x-filament::button size="xs" color="gray" tag="a" :href="$row['ads_url']">Reklamy</x-filament::button>
                    @endif
                    <x-filament::button size="xs" color="gray" tag="a" :href="$row['client_url']">Karta klienta</x-filament::button>
                </div>
            </x-slot>

            @include('filament.tools.partials.briefing', ['briefing' => $row['briefing']])
        </x-filament::section>
    @empty
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $date->isToday() ? 'Agent dnes přehled ještě nenapsal. Běží ve všední dny v 6:50 a jen u klientů s reklamami nebo vyplněnou kartou.' : 'Pro tento den přehled není.' }}
            </p>
        </x-filament::section>
    @endforelse
</x-filament-panels::page>
