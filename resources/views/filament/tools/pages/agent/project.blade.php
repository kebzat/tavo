{{--
    Vlákna projektu vlevo, terminál vybraného vlákna vpravo. Seznam se
    obnovuje každých 15 s, terminál je wire:ignore, aby ho Livewire při
    obnově nepřekreslil a spojení nespadlo.
--}}
<x-filament-panels::page>
    @vite('resources/js/agent-terminal.js')

    @if ($this->error())
        <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="danger">
            <x-slot name="heading">Agent je nedostupný</x-slot>
            <p class="text-sm text-gray-600 dark:text-gray-300">{{ $this->error() }}</p>
        </x-filament::section>
    @else
        <div class="grid gap-4 lg:grid-cols-[17rem_minmax(0,1fr)]">
            <div wire:poll.15s class="flex flex-col gap-2">
                @forelse ($this->threadItems() as $thread)
                    <div wire:key="vlakno-{{ $thread['id'] }}"
                         @class([
                             'group rounded-xl p-3 ring-1 transition',
                             'bg-primary-50 ring-primary-500 dark:bg-primary-500/10' => $thread['selected'],
                             'bg-white ring-gray-950/5 hover:ring-gray-950/20 dark:bg-gray-900 dark:ring-white/10' => ! $thread['selected'],
                         ])>
                        <button type="button" wire:click="selectThread('{{ $thread['id'] }}')" class="block w-full text-left">
                            <span class="flex items-center gap-2">
                                <span @class([
                                    'size-2 shrink-0 rounded-full',
                                    'bg-success-500' => $thread['claude'],
                                    'bg-gray-400' => ! $thread['claude'],
                                ]) title="{{ $thread['status'] }}"></span>
                                <span class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $thread['nazev'] }}</span>
                            </span>
                            @if ($thread['meta'])
                                <span class="mt-1 block truncate pl-4 text-xs text-gray-500 dark:text-gray-400">{{ $thread['meta'] }}</span>
                            @endif
                        </button>

                        <div class="mt-2 flex gap-3 pl-4 text-xs">
                            <button type="button" wire:click="mountAction('renameThread', { thread: '{{ $thread['id'] }}' })"
                                    class="text-gray-500 hover:text-gray-900 dark:hover:text-white">Přejmenovat</button>
                            <button type="button" wire:click="mountAction('closeThread', { thread: '{{ $thread['id'] }}' })"
                                    class="text-gray-500 hover:text-danger-600">Ukončit</button>
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl bg-white p-4 text-sm text-gray-500 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:text-gray-400 dark:ring-white/10">
                        Zatím žádné vlákno. Založte ho tlačítkem <strong>Nové vlákno</strong>.
                    </div>
                @endforelse

                <p class="mt-2 px-1 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                    Vlákno běží dál i po zavření stránky. Ve stejném vlákně můžete být s Pavlem zároveň, každý vidí totéž.
                </p>
            </div>

            <div wire:ignore
                 x-data="{
                     state: 'connecting',
                     full: false,
                     term: null,
                     init() {
                         const start = () => {
                             this.term = new window.TavoAgentTerminal(this.$refs.screen, {
                                 url: () => this.$wire.terminalUrl(),
                                 onState: (state) => this.state = state,
                             });
                             this.term.connect();
                         };
                         window.TavoAgentTerminal ? start() : window.addEventListener('tavo-agent-terminal-ready', start, { once: true });
                     },
                     toggleFull() {
                         this.full = ! this.full;
                         this.$nextTick(() => { this.term?.resize(); this.term?.focus(); });
                     },
                 }"
                 x-on:agent-thread-selected.window="term?.connect()"
                 x-bind:class="full ? 'fixed inset-0 z-50 flex flex-col bg-terminal p-3' : 'relative flex flex-col'">

                <div class="flex items-center justify-between gap-3 rounded-t-xl border-b border-white/10 bg-terminal px-4 py-2 text-xs text-gray-400">
                    <span x-text="{
                        connecting: 'Připojuji…',
                        reconnecting: 'Spojení spadlo, připojuji znovu…',
                        closed: 'Odpojeno',
                        none: 'Žádné vlákno',
                        open: 'Připojeno',
                    }[state] ?? ''"></span>

                    <span class="flex items-center gap-4">
                        <button type="button" x-show="state === 'closed'" x-on:click="term?.connect()" class="font-semibold text-primary-400 hover:text-primary-300">Připojit znovu</button>
                        <button type="button" x-on:click="toggleFull()" class="hover:text-white" x-text="full ? 'Zmenšit' : 'Celá obrazovka'"></button>
                    </span>
                </div>

                <div class="relative grow rounded-b-xl bg-terminal p-2">
                    <div x-ref="screen" x-bind:class="full ? 'h-[calc(100vh-5rem)]' : 'h-[calc(100vh-17rem)] min-h-[26rem]'"></div>

                    <div x-show="state === 'none'" x-cloak
                         class="absolute inset-0 flex items-center justify-center p-6 text-center text-sm text-gray-400">
                        Vyberte vlákno vlevo, nebo založte nové tlačítkem Nové vlákno.
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
