{{-- Reklamní grafika: zadání a návrhy po kolech (první zadání, pak úpravy). Během práce agenta se obnovuje sama. --}}
<x-filament-panels::page>
    @vite('resources/js/agent-terminal.js')

    <div @if ($this->latest()->isRunning()) wire:poll.15s @endif class="space-y-6">
        @foreach ($this->rounds() as $round)
            <x-filament::section :icon="$loop->first ? 'heroicon-o-photo' : 'heroicon-o-pencil-square'">
                <x-slot name="heading">
                    {{ $loop->first ? 'Návrhy' : 'Úprava '.($loop->index) }}
                    <x-filament::badge :color="$round->statusColor()" class="ml-2 inline-flex align-middle">{{ $round->statusLabel() }}</x-filament::badge>
                </x-slot>
                <x-slot name="description">
                    @if ($loop->first)
                        {{ $round->brief }}
                    @else
                        Pokyn: {{ $round->instruction }} · {{ $round->author }}, {{ $round->created_at->format('j. n. H:i') }}
                    @endif
                </x-slot>

                @if ($round->isRunning())
                    <div class="flex items-center gap-3 rounded-lg bg-warning-50 px-4 py-3 text-sm text-warning-800 dark:bg-warning-500/10 dark:text-warning-300">
                        <x-filament::loading-indicator class="h-5 w-5" />
                        <span>
                            Agent pracuje od {{ $round->started_at?->format('H:i') ?? 'teď' }}: prochází web klienta, staví návrhy a posílá je do Figmy.
                            Trvá to zhruba 10–30 minut. Stránku můžete zavřít, výsledek tady počká.
                        </span>
                    </div>

                    {{-- Terminál úlohy: výchozí jen ke sledování, Zasáhnout ho přepne na psaní.
                         wire:ignore, ať ho obnova stránky (wire:poll) nepřekreslí.
                         Úloha ve frontě ještě okno nemá: zkouší se znovu. --}}
                    <div wire:ignore wire:key="terminal-{{ $round->getKey() }}"
                         class="mt-4 overflow-hidden rounded-xl bg-terminal shadow-sm ring-1 ring-gray-950/10"
                         x-data="{
                             state: 'connecting',
                             full: false,
                             seen: false,
                             write: false,
                             term: null,
                             init() {
                                 const start = () => {
                                     this.term = new window.TavoAgentTerminal(this.$refs.screen, {
                                         url: () => this.$wire.terminalUrl(this.write),
                                         onState: (state) => {
                                             this.state = state;
                                             if (state === 'open') this.seen = true;
                                             // Okno zmizelo (agent doběhl): rovnou ukázat návrhy.
                                             if (! this.$el.isConnected) return; // kolo doběhlo, blok zmizel
                                             if (state === 'none' && this.seen) this.$wire.$refresh();
                                             if (state === 'none' || state === 'closed') setTimeout(() => this.$el.isConnected && this.term.connect(), 5000);
                                         },
                                     });
                                     this.term.connect();
                                 };
                                 window.TavoAgentTerminal ? start() : window.addEventListener('tavo-agent-terminal-ready', start, { once: true });
                             },
                             toggleWrite() {
                                 this.write = ! this.write;
                                 this.term?.connect();
                             },
                             toggleFull() {
                                 this.full = ! this.full;
                                 this.$nextTick(() => this.term?.resize());
                             },
                         }"
                         x-bind:class="full && 'fixed inset-0 z-50 rounded-none'">
                        <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 border-b border-white/10 px-4 py-2.5">
                            <p class="text-sm font-semibold text-white">Co agent dělá</p>
                            <div class="flex items-center gap-3 whitespace-nowrap text-xs text-gray-400">
                                <span class="hidden sm:inline" x-text="{
                                    connecting: 'Připojuji…',
                                    open: write ? 'Píšete agentovi' : 'Živě, jen sledujete',
                                    reconnecting: 'Spojení spadlo, připojuji znovu…',
                                    closed: 'Odpojeno, zkouším znovu…',
                                    none: seen ? 'Agent dopsal, načítám návrhy…' : 'Agent se chystá…',
                                }[state] ?? ''"></span>
                                <button type="button" x-on:click="toggleFull()" class="hover:text-white" x-text="full ? 'Zmenšit' : 'Celá obrazovka'"></button>
                                <x-filament::button size="xs" x-on:click="toggleWrite()" x-show="! write" icon="heroicon-m-hand-raised">Zasáhnout</x-filament::button>
                                <x-filament::button size="xs" color="gray" x-on:click="toggleWrite()" x-show="write" x-cloak icon="heroicon-m-eye">Jen sledovat</x-filament::button>
                            </div>
                        </div>

                        <p x-show="write" x-cloak class="border-b border-white/10 bg-primary-500/15 px-4 py-2 text-xs text-gray-200">
                            Píšete přímo agentovi. Klávesou Esc ho zastavíte, pak napište, co má udělat jinak, a odešlete Enterem.
                            Úloha skončí, až agent odevzdá návrhy, na každou vaši zprávu má navíc 15 minut.
                        </p>

                        <div class="relative px-2 pb-2 pt-1">
                            <div x-ref="screen" x-bind:class="full ? 'h-[calc(100vh-3.5rem)]' : 'h-[32rem]'"></div>

                            <div x-show="state === 'none' && ! seen" x-cloak
                                 class="absolute inset-0 flex items-center justify-center p-6 text-center text-sm text-gray-400">
                                Agent se ještě nerozběhl (úloha může čekat, než doběhne jiná v tomtéž projektu). Terminál se připojí sám.
                            </div>
                        </div>
                    </div>
                @endif

                @if ($round->isFailed() && $round->error)
                    <div class="mb-4 rounded-lg bg-danger-50 px-4 py-3 text-sm text-danger-700 dark:bg-danger-500/10 dark:text-danger-300">
                        Nepovedlo se: {{ $round->error }}
                    </div>
                @endif

                @if (filled($round->outputs))
                    <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($round->outputs as $output)
                            <figure class="flex flex-col overflow-hidden rounded-xl bg-white ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                                <a href="{{ $this->imageUrl($round, $output['soubor']) }}" target="_blank" class="flex h-72 items-center justify-center bg-gray-100 p-3 dark:bg-gray-800">
                                    <img src="{{ $this->imageUrl($round, $output['soubor']) }}" alt="{{ $output['nazev'] }}" loading="lazy" class="max-h-full max-w-full rounded object-contain shadow-sm">
                                </a>
                                <figcaption class="flex flex-1 flex-col gap-2 p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="font-semibold text-gray-950 dark:text-white">{{ $output['nazev'] ?: $output['soubor'] }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $output['format'] }}</p>
                                        </div>
                                        <x-filament::icon-button
                                            tag="a"
                                            :href="$this->imageUrl($round, $output['soubor'])"
                                            :download="$output['soubor']"
                                            icon="heroicon-o-arrow-down-tray"
                                            color="gray"
                                            label="Stáhnout PNG"
                                            tooltip="Stáhnout PNG"
                                        />
                                    </div>
                                    @if ($output['text'])
                                        <p class="text-sm text-gray-700 dark:text-gray-300"><span class="text-gray-500 dark:text-gray-400">Text k příspěvku:</span> {{ $output['text'] }}</p>
                                    @endif
                                </figcaption>
                            </figure>
                        @endforeach
                    </div>
                @endif

                @if ($round->result)
                    <div class="mt-4 rounded-lg bg-gray-50 px-4 py-3 text-sm whitespace-pre-line text-gray-700 dark:bg-white/5 dark:text-gray-300">{{ $round->result }}</div>
                @endif

                @if ($round->figma_url && ! $round->isRunning())
                    <p class="mt-3 text-sm">
                        <a href="{{ $round->figma_url }}" target="_blank" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">Otevřít ve Figmě</a>
                        <span class="text-gray-500 dark:text-gray-400">· vrstvy jdou upravit, soubor je v konceptech účtu Taveo</span>
                    </p>
                @endif
            </x-filament::section>
        @endforeach

        <x-filament::section icon="heroicon-o-clipboard-document-list" collapsible collapsed>
            <x-slot name="heading">Zadání</x-slot>
            <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                <div><dt class="text-gray-500 dark:text-gray-400">Formáty</dt><dd>{{ $record->formatsLabel() }}</dd></div>
                <div><dt class="text-gray-500 dark:text-gray-400">Počet návrhů</dt><dd>{{ $record->variants }}{{ $record->every_format ? ', každý ve všech formátech' : '' }}</dd></div>
                <div><dt class="text-gray-500 dark:text-gray-400">Web klienta</dt><dd>{{ $record->website_url ?: 'neuvedený' }}</dd></div>
                <div><dt class="text-gray-500 dark:text-gray-400">Písma</dt><dd>{{ $record->fonts ?: 'podle webu' }}</dd></div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Barvy</dt>
                    <dd class="flex flex-wrap items-center gap-2">
                        @forelse ($record->colors ?? [] as $color)
                            <span class="inline-flex items-center gap-1"><span class="inline-block h-4 w-4 rounded ring-1 ring-gray-950/10" style="background: {{ $color }}"></span>{{ $color }}</span>
                        @empty
                            podle webu
                        @endforelse
                    </dd>
                </div>
                <div><dt class="text-gray-500 dark:text-gray-400">Podklady</dt><dd>{{ collect($record->assets ?? [])->pluck('name')->implode(', ') ?: 'žádné' }}</dd></div>
                <div><dt class="text-gray-500 dark:text-gray-400">AI obrázky</dt><dd>{{ $record->ai_images ? 'povolené' : 'ne' }}</dd></div>
                <div><dt class="text-gray-500 dark:text-gray-400">Projekt agenta</dt><dd>{{ $record->project }}, složka predani/{{ $record->folder() }}</dd></div>
                @if ($record->inspiration)
                    <div class="sm:col-span-2"><dt class="text-gray-500 dark:text-gray-400">Inspirace</dt><dd class="whitespace-pre-line">{{ $record->inspiration }}</dd></div>
                @endif
            </dl>
        </x-filament::section>
    </div>
</x-filament-panels::page>
