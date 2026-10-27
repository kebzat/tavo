<x-filament-panels::page>
    @php($listing = $this->listing())
    @php($requests = $this->clientRequests())

    @if ($requests['queued']->isNotEmpty())
        <div wire:poll.5s>
            <x-filament::section icon="heroicon-o-arrow-path" icon-color="info" compact>
                <x-slot name="heading">Zakládají se agenti ({{ $requests['queued']->count() }})</x-slot>
                <x-slot name="description">Na pozadí po jednom, pár sekund na klienta. Seznam se obnoví sám.</x-slot>
                <p class="text-sm text-gray-600 dark:text-gray-300">{{ $requests['queued']->pluck('name')->implode(', ') }}</p>
            </x-filament::section>
        </div>
    @endif

    @if ($requests['failed']->isNotEmpty())
        <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="warning" compact>
            <x-slot name="heading">Agent se nezaložil ({{ $requests['failed']->flatten()->count() }})</x-slot>
            <x-slot name="description">Znovu jde zkusit tlačítkem Založit agenty klientům vpravo nahoře.</x-slot>
            <ul class="space-y-1 text-sm text-gray-600 dark:text-gray-300">
                @foreach ($requests['failed'] as $error => $clients)
                    <li><span class="font-medium text-gray-950 dark:text-white">{{ $clients->pluck('name')->implode(', ') }}</span>: {{ $error }}</li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif

    @if ($listing['error'])
        <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="danger">
            <x-slot name="heading">Agent je nedostupný</x-slot>
            <p class="text-sm text-gray-600 dark:text-gray-300">{{ $listing['error'] }}</p>
        </x-filament::section>
    @elseif ($listing['total'] === 0)
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">Zatím žádný projekt. Přidejte ho tlačítkem vpravo nahoře.</p>
        </x-filament::section>
    @else
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                @if ($listing['pages'] > 1)
                    {{ $listing['from'] }}–{{ $listing['to'] }} z {{ $listing['total'] }} projektů
                @else
                    {{ $listing['total'] }} {{ match (true) { $listing['total'] === 1 => 'projekt', $listing['total'] < 5 => 'projekty', default => 'projektů' } }}
                @endif
            </p>

            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-500 dark:text-gray-400">Zobrazit</span>
                    <div class="inline-flex rounded-lg bg-gray-100 p-0.5 dark:bg-white/5">
                        @foreach (\App\Filament\Tools\Pages\Agent\AgentProjects::PER_PAGE as $value => $label)
                            <button type="button" wire:click="$set('perPage', @js((string) $value))"
                                    @class([
                                        'rounded-md px-2.5 py-1 text-sm font-medium transition',
                                        'bg-white text-gray-950 shadow-sm dark:bg-gray-800 dark:text-white' => $perPage === (string) $value,
                                        'text-gray-500 hover:text-gray-950 dark:text-gray-400 dark:hover:text-white' => $perPage !== (string) $value,
                                    ])>{{ $label }}</button>
                        @endforeach
                    </div>
                </div>

                <div class="inline-flex rounded-lg bg-gray-100 p-0.5 dark:bg-white/5">
                    @foreach (['grid' => ['Mřížka', 'heroicon-m-squares-2x2'], 'list' => ['Seznam', 'heroicon-m-bars-3']] as $value => [$label, $icon])
                        <button type="button" wire:click="$set('display', @js($value))"
                                @class([
                                    'inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-sm font-medium transition',
                                    'bg-white text-gray-950 shadow-sm dark:bg-gray-800 dark:text-white' => $display === $value,
                                    'text-gray-500 hover:text-gray-950 dark:text-gray-400 dark:hover:text-white' => $display !== $value,
                                ])>
                            <x-filament::icon :icon="$icon" class="h-4 w-4" />
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        @if ($display === 'list')
            <div class="divide-y divide-gray-950/5 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:divide-white/10 dark:bg-gray-900 dark:ring-white/10">
                @foreach ($listing['projects'] as $project)
                    <a href="{{ $project['url'] }}" wire:key="list-{{ $project['slug'] }}"
                       class="group flex flex-col gap-1 px-5 py-3 transition hover:bg-gray-50 sm:flex-row sm:items-center sm:gap-4 dark:hover:bg-white/5">
                        <div class="min-w-0 sm:w-1/3">
                            <p class="truncate text-sm font-bold text-gray-950 group-hover:text-primary-600 dark:text-white">
                                {{ $project['client'] ?? $project['slug'] }}
                            </p>
                            @if ($project['client'])
                                <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $project['slug'] }}</p>
                            @endif
                        </div>

                        <p class="min-w-0 flex-1 text-sm text-gray-600 dark:text-gray-300">{{ $project['threads_label'] }}</p>

                        <div class="shrink-0">
                            <x-filament::badge :color="$project['type_color']">{{ $project['type_label'] }}</x-filament::badge>
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($listing['projects'] as $project)
                    <a href="{{ $project['url'] }}" wire:key="grid-{{ $project['slug'] }}"
                       class="group flex flex-col gap-3 rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 transition hover:ring-primary-500 dark:bg-gray-900 dark:ring-white/10">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-base font-bold text-gray-950 group-hover:text-primary-600 dark:text-white">
                                    {{ $project['client'] ?? $project['slug'] }}
                                </p>
                                @if ($project['client'])
                                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $project['slug'] }}</p>
                                @endif
                            </div>

                            <x-filament::badge :color="$project['type_color']">{{ $project['type_label'] }}</x-filament::badge>
                        </div>

                        <p class="text-sm text-gray-600 dark:text-gray-300">{{ $project['threads_label'] }}</p>
                    </a>
                @endforeach
            </div>
        @endif

        @if ($listing['pages'] > 1)
            <nav class="flex flex-wrap items-center justify-center gap-1" aria-label="Stránky projektů">
                <x-filament::button size="sm" color="gray" icon="heroicon-m-chevron-left" :disabled="$listing['page'] === 1" wire:click="goToPage({{ $listing['page'] - 1 }})">
                    Předchozí
                </x-filament::button>
                @for ($i = 1; $i <= $listing['pages']; $i++)
                    <x-filament::button size="sm" :color="$i === $listing['page'] ? 'primary' : 'gray'" wire:click="goToPage({{ $i }})">{{ $i }}</x-filament::button>
                @endfor
                <x-filament::button size="sm" color="gray" icon="heroicon-m-chevron-right" icon-position="after" :disabled="$listing['page'] === $listing['pages']" wire:click="goToPage({{ $listing['page'] + 1 }})">
                    Další
                </x-filament::button>
            </nav>
        @endif
    @endif

    @if ($output)
        <x-filament::section icon="heroicon-o-key" icon-color="warning">
            <x-slot name="heading">Co ještě udělat</x-slot>
            <x-slot name="description">Výstup z posledního přidání projektu. U repozitáře přidejte klíče na GitHubu a projekt přidejte znovu se stejným názvem.</x-slot>
            <pre class="overflow-x-auto rounded-lg bg-gray-950 p-4 text-xs leading-relaxed whitespace-pre-wrap text-gray-100">{{ $output }}</pre>
        </x-filament::section>
    @endif
</x-filament-panels::page>
