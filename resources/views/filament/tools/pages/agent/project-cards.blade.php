{{-- Projekty klientů nebo interní projekty jako mřížka, nebo seznam (přepínač u Klientů). --}}
@if ($display === 'list')
    <div class="divide-y divide-gray-950/5 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:divide-white/10 dark:bg-gray-900 dark:ring-white/10">
        @foreach ($projects as $project)
            <a href="{{ $project['url'] }}" wire:key="list-{{ $group }}-{{ $project['slug'] }}"
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
        @foreach ($projects as $project)
            <a href="{{ $project['url'] }}" wire:key="grid-{{ $group }}-{{ $project['slug'] }}"
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
