{{-- Podklady pro mozek: průběh práce agenta, jeho shrnutí a návrhy poznatků ke schválení. Během práce se obnovuje sama. --}}
<x-filament-panels::page>
    @vite('resources/js/agent-terminal.js')

    <div @if ($this->record->isRunning()) wire:poll.15s @endif class="space-y-6">
        <x-filament::section icon="heroicon-o-cpu-chip">
            <x-slot name="heading">
                Agent
                <x-filament::badge :color="$this->record->statusColor()" class="ml-2 inline-flex align-middle">{{ $this->record->statusLabel() }}</x-filament::badge>
            </x-slot>
            @if ($this->record->note)
                <x-slot name="description">Poznámka pro agenta: {{ $this->record->note }}</x-slot>
            @endif

            @if ($this->record->isRunning())
                <div class="flex items-center gap-3 rounded-lg bg-warning-50 px-4 py-3 text-sm text-warning-800 dark:bg-warning-500/10 dark:text-warning-300">
                    <x-filament::loading-indicator class="h-5 w-5" />
                    <span>
                        Agent čte od {{ $this->record->started_at?->format('H:i') ?? 'teď' }}, třídí a posílá návrhy poznatků. Objevují se níž průběžně.
                        Trvá to podle délky 5–30 minut. Stránku můžete zavřít.
                    </span>
                </div>

                @include('filament.tools.partials.agent-terminal', ['key' => 'zdroj-'.$this->record->getKey(), 'done' => 'Agent dopsal, načítám návrhy…'])
            @endif

            @if ($this->record->isFailed() && $this->record->error)
                <div class="rounded-lg bg-danger-50 px-4 py-3 text-sm text-danger-700 dark:bg-danger-500/10 dark:text-danger-300">
                    Nepovedlo se: {{ $this->record->error }}
                </div>
            @endif

            @if ($this->record->result)
                <div @class(['rounded-lg bg-gray-50 px-4 py-3 text-sm whitespace-pre-line text-gray-700 dark:bg-white/5 dark:text-gray-300', 'mt-4' => $this->record->isRunning() || $this->record->isFailed()])>{{ $this->record->result }}</div>
            @endif
        </x-filament::section>

        <x-filament::section icon="heroicon-o-light-bulb">
            <x-slot name="heading">Poznatky z těchto podkladů ({{ $this->entries->count() }})</x-slot>
            <x-slot name="description">Schválené začnou platit a agenti je dostanou. Upravit jde každý, než ho schválíte.</x-slot>

            @forelse ($this->entries as $entry)
                <div wire:key="entry-{{ $entry->getKey() }}" class="flex flex-col gap-3 border-t border-gray-100 py-4 first:border-t-0 first:pt-0 sm:flex-row sm:items-start sm:justify-between dark:border-white/5">
                    <div class="min-w-0 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-filament::badge color="gray">{{ $entry->layerLabel() }}{{ $entry->client ? ': '.$entry->client->name : '' }}</x-filament::badge>
                            @if ($entry->replaces_id && $entry->isProposed())
                                <x-filament::badge color="warning">Nové znění #{{ $entry->replaces_id }}</x-filament::badge>
                            @elseif (! $entry->isProposed())
                                <x-filament::badge :color="$entry->statusColor()">{{ $entry->statusLabel() }}</x-filament::badge>
                            @endif
                        </div>
                        <p class="font-semibold text-gray-950 dark:text-white">{{ $entry->title }}</p>
                        <p class="text-sm whitespace-pre-line text-gray-700 dark:text-gray-300">{{ $entry->body }}</p>
                        @if ($entry->origin)
                            <p class="text-xs text-gray-500 dark:text-gray-400">Odkud: {{ $entry->origin }}</p>
                        @endif
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        @if ($entry->isProposed())
                            <x-filament::button size="sm" color="success" icon="heroicon-m-check" wire:click="approve({{ $entry->getKey() }})">Schválit</x-filament::button>
                            <x-filament::button size="sm" color="gray" icon="heroicon-m-x-mark" wire:click="reject({{ $entry->getKey() }})">Zamítnout</x-filament::button>
                        @endif
                        <x-filament::button size="sm" color="gray" tag="a" :href="\App\Filament\Tools\Resources\KnowledgeEntries\KnowledgeEntryResource::getUrl('edit', ['record' => $entry])">Upravit</x-filament::button>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $this->record->isRunning() ? 'Agent zatím nic neposlal.' : 'Z těchto podkladů žádný poznatek nevzešel.' }}
                </p>
            @endforelse
        </x-filament::section>

        @if ($this->record->body)
            <x-filament::section icon="heroicon-o-document-text" collapsible collapsed>
                <x-slot name="heading">Vložený text</x-slot>
                <div class="max-h-[32rem] overflow-y-auto text-sm whitespace-pre-line text-gray-700 dark:text-gray-300">{{ $this->record->body }}</div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
