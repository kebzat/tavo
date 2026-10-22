<x-filament-panels::page>
    @php($listing = $this->listing())

    @if ($listing['error'])
        <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="danger">
            <x-slot name="heading">Agent je nedostupný</x-slot>
            <p class="text-sm text-gray-600 dark:text-gray-300">{{ $listing['error'] }}</p>
        </x-filament::section>
    @elseif ($listing['projects'] === [])
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">Zatím žádný projekt. Přidejte ho tlačítkem vpravo nahoře.</p>
        </x-filament::section>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($listing['projects'] as $project)
                <a href="{{ $project['url'] }}"
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

    @if ($output)
        <x-filament::section icon="heroicon-o-key" icon-color="warning">
            <x-slot name="heading">Co ještě udělat</x-slot>
            <x-slot name="description">Výstup z posledního přidání projektu. U repozitáře přidejte klíče na GitHubu a projekt přidejte znovu se stejným názvem.</x-slot>
            <pre class="overflow-x-auto rounded-lg bg-gray-950 p-4 text-xs leading-relaxed whitespace-pre-wrap text-gray-100">{{ $output }}</pre>
        </x-filament::section>
    @endif
</x-filament-panels::page>
