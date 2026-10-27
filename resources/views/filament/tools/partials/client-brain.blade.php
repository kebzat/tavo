{{-- Mozek a archiv klienta (bez klienta: Mozek TAVEO, v projektu skillu: Mozek skillu) v liště nad terminálem: tlačítka a panel z boku. Terminál zůstává otevřený. --}}
<div class="flex flex-wrap items-center gap-2">
    <x-filament::modal id="mozek-klienta" slide-over width="2xl">
        <x-slot name="trigger">
            <x-filament::button size="sm" color="gray" icon="heroicon-m-light-bulb" :badge="$proposed->count() ?: null" badge-color="warning">
                {{ $skill ? 'Mozek skillu' : ($client ? 'Mozek klienta' : 'Mozek TAVEO') }}
            </x-filament::button>
        </x-slot>

        <x-slot name="heading">{{ $heading }}</x-slot>
        <x-slot name="description">
            {{ match (true) {
                $skill !== null => 'Co skill umí a co se naučil u klientů. Dostane to tady na začátku konverzace a agenti klientů přes /'.$skill->slug.'. Návrhy začnou platit, až je schválíte.',
                $client !== null => 'Tohle agent dostane na začátku každé konverzace v projektu. Návrhy začnou platit, až je schválíte.',
                default => 'Projekt není klient. Agent tu dostane firemní poznatky, postupy a archiv firmy (nejnovější, zbytek si dohledá). Návrhy začnou platit, až je schválíte.',
            } }}
        </x-slot>

        <div class="space-y-8">
            <div class="flex flex-wrap items-center gap-2">
                {{ $this->uploadAction }}
                @if ($clientUrl)
                    <x-filament::button size="sm" color="gray" outlined tag="a" :href="$clientUrl" icon="heroicon-m-building-office-2">Karta klienta</x-filament::button>
                @endif
                @if ($skillUrl)
                    <x-filament::button size="sm" color="gray" outlined tag="a" :href="$skillUrl" icon="heroicon-m-adjustments-horizontal">Pokyny skillu</x-filament::button>
                @endif
            </div>

            @if ($proposed->isNotEmpty())
                <section class="space-y-3">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Ke schválení ({{ $proposed->count() }})</h3>
                    @foreach ($proposed as $entry)
                        <div wire:key="navrh-{{ $entry->getKey() }}" class="rounded-xl bg-warning-50 p-4 ring-1 ring-warning-600/20 dark:bg-warning-500/10 dark:ring-warning-400/20">
                            <p class="font-semibold text-gray-950 dark:text-white">{{ $entry->title }}</p>
                            @if ($skill && $entry->client)
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $entry->client->name }}</p>
                            @endif
                            <p class="mt-1 text-sm whitespace-pre-line text-gray-700 dark:text-gray-300">{{ $entry->body }}</p>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $entry->originLabel() }}</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <x-filament::button size="xs" color="success" icon="heroicon-m-check" wire:click="approve({{ $entry->getKey() }})">Schválit</x-filament::button>
                                <x-filament::button size="xs" color="gray" icon="heroicon-m-x-mark" wire:click="reject({{ $entry->getKey() }})">Zamítnout</x-filament::button>
                                <x-filament::button size="xs" color="gray" outlined tag="a" :href="\App\Filament\Tools\Resources\KnowledgeEntries\KnowledgeEntryResource::getUrl('edit', ['record' => $entry])">Upravit</x-filament::button>
                            </div>
                        </div>
                    @endforeach
                </section>
            @endif

            <section class="space-y-3">
                <div class="flex items-baseline justify-between gap-3">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $skill ? 'Know-how skillu' : ($client ? 'Co agent o klientovi ví' : 'Firemní poznatky a postupy') }} ({{ $approvedCount }})</h3>
                    <a href="{{ $entriesUrl }}" class="text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400">Všechny poznatky</a>
                </div>
                @forelse ($approved as $entry)
                    <div wire:key="poznatek-{{ $entry->getKey() }}" class="border-t border-gray-100 pt-3 first-of-type:border-t-0 first-of-type:pt-0 dark:border-white/5">
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">
                            {{ $entry->title }}
                            @unless ($client || $skill)
                                <x-filament::badge color="gray" size="sm" class="ml-1 inline-flex align-middle">{{ $entry->always ? 'Vždy v hlavě' : $entry->layerLabel() }}</x-filament::badge>
                            @endunless
                        </p>
                        <p class="mt-0.5 line-clamp-3 text-sm text-gray-600 dark:text-gray-400">{{ $entry->body }}</p>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $skill
                        ? 'Zatím nic schváleného. Nahrajte do archivu skillu case studies, výsledky nebo poznámky, knihovník z nich navrhne poznatky.'
                        : 'Zatím nic schváleného. Poznatky přibudou z archivu, z ClickUpu a z konverzací s agentem.' }}</p>
                @endforelse
                @if ($approvedCount > $approved->count())
                    <p class="text-xs text-gray-500 dark:text-gray-400">Dalších {{ $approvedCount - $approved->count() }} je ve Všech poznatcích.</p>
                @endif
            </section>

            @if ($skill)
                <section class="space-y-3">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Zkušenosti z klientů ({{ $experienceCount }})</h3>
                    @forelse ($experience as $entry)
                        <div wire:key="zkusenost-{{ $entry->getKey() }}" class="border-t border-gray-100 pt-3 first-of-type:border-t-0 first-of-type:pt-0 dark:border-white/5">
                            <p class="text-sm font-semibold text-gray-950 dark:text-white">
                                {{ $entry->title }}
                                <x-filament::badge color="gray" size="sm" class="ml-1 inline-flex align-middle">{{ $entry->client?->name }}</x-filament::badge>
                            </p>
                            <p class="mt-0.5 line-clamp-3 text-sm text-gray-600 dark:text-gray-400">{{ $entry->body }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">Zatím žádné. Přibudou, když agenti klientů při práci se skillem navrhnou, co u klienta fungovalo, a vy to schválíte.</p>
                    @endforelse
                </section>
            @endif

            <section class="space-y-3">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $skill ? 'Archiv skillu' : ($client ? 'Archiv' : 'Archiv firmy') }} ({{ $sources->count() }})</h3>
                @forelse ($sources as $source)
                    <div wire:key="dokument-{{ $source->getKey() }}" class="rounded-xl p-4 ring-1 ring-gray-950/5 dark:ring-white/10">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <a href="{{ $this->sourceUrl($source) }}" class="font-semibold text-gray-950 hover:underline dark:text-white">{{ $source->title }}</a>
                            <x-filament::badge :color="$source->statusColor()" size="sm">{{ $source->statusLabel() }}</x-filament::badge>
                        </div>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ collect(['#'.$source->getKey(), $skill ? $source->client?->name : null, $source->categoryLabel(), $source->document_date ? 'k '.$source->document_date->format('j. n. Y') : null, 'nahrál '.($source->author ?: '?').' '.$source->created_at->format('j. n.')])->filter()->implode(' · ') }}
                        </p>
                        @if ($source->summary)
                            <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $source->summary }}</p>
                        @endif
                        @if ($source->files)
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($source->files as $index => $file)
                                    <x-filament::button size="xs" color="gray" icon="heroicon-m-arrow-down-tray" wire:click="download({{ $source->getKey() }}, {{ $index }})">{{ $file['name'] ?? basename($file['path']) }}</x-filament::button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">Archiv je prázdný. Nahrajte {{ match (true) { $skill !== null => 'case studies, výsledky klientů, vzorové weby nebo poznámky', $client !== null => 'vizi, strategii, data, výsledky nebo marže', default => 'firemní strategii, ceník, čísla nebo zápisy' } }}, nebo dokument pošlete agentovi do konverzace a on ho uloží sám.</p>
                @endforelse
            </section>
        </div>
    </x-filament::modal>

    {{ $this->uploadAction }}

    <x-filament-actions::modals />
</div>
