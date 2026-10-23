{{--
    Terminál přes celou šířku, pod ním konverzace projektu. Lišta i seznam
    se obnovují každých 15 s, terminál je wire:ignore, aby ho Livewire při
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
        @if ($this->details())
            @livewire('tools.agent-handovers', ['project' => $project, 'type' => $this->details()['typ']], key('hotova-prace'))
        @endif

        <div wire:poll.15s class="flex flex-col gap-6">
            @php($current = $this->current())

            <div class="overflow-hidden rounded-xl bg-terminal shadow-sm ring-1 ring-gray-950/10">
                {{-- O čem je otevřená konverzace a nová konverzace jedním klikem. --}}
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 px-4 py-2.5">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-white">{{ $current['nazev'] ?? 'Připravuji konverzaci…' }}</p>
                        @if ($current['meta'] ?? null)
                            <p class="truncate text-xs text-gray-400">{{ $current['meta'] }}</p>
                        @endif
                    </div>

                    <x-filament::button size="sm" icon="heroicon-m-plus" wire:click="newConversation" wire:target="newConversation,autoStart,select">
                        Nová konverzace
                    </x-filament::button>
                </div>

                <div wire:ignore
                     x-data="{
                         state: 'connecting',
                         full: false,
                         term: null,
                         started: false,
                         copied: null,
                         init() {
                             const start = () => {
                                 this.term = new window.TavoAgentTerminal(this.$refs.screen, {
                                     url: () => this.$wire.terminalUrl(),
                                 onCopy: (result) => {
                                     this.copied = result;
                                     clearTimeout(this.copiedTimer);
                                     if (result === 'copied') this.copiedTimer = setTimeout(() => this.copied = null, 2500);
                                 },
                                     onState: (state) => {
                                         this.state = state;
                                         // Žádná konverzace neběží: založit novou, ať se dá hned psát.
                                         if (state === 'none' && ! this.started) {
                                             this.started = true;
                                             this.state = 'starting';
                                             this.$wire.autoStart();
                                         }
                                     },
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
                     x-on:agent-window-selected.window="term?.connect()"
                     x-bind:class="full ? 'fixed inset-0 z-50 bg-terminal p-3' : 'relative p-2'">

                    <div x-ref="screen" x-bind:class="full ? 'h-[calc(100vh-1.5rem)]' : 'h-[calc(100vh-22rem)] min-h-[26rem]'"></div>

                    <div class="absolute top-3 right-4 flex items-center gap-3 rounded-md bg-terminal/90 px-2 py-1 text-xs text-gray-400">
                        <span x-show="copied === 'copied'" x-cloak class="font-semibold text-success-400">Zkopírováno</span>
                        <span x-show="copied === 'pending'" x-cloak class="font-semibold text-primary-400">Zkopírujete stiskem ⌘C</span>
                        <span x-show="state !== 'open'" x-text="{
                            connecting: 'Připojuji…',
                            starting: 'Připravuji konverzaci…',
                            reconnecting: 'Spojení spadlo, připojuji znovu…',
                            closed: 'Odpojeno',
                            none: 'Žádná konverzace',
                        }[state] ?? ''"></span>
                        <button type="button" x-show="state === 'closed'" x-on:click="term?.connect()" class="font-semibold text-primary-400 hover:text-primary-300">Připojit znovu</button>
                        <button type="button" x-on:click="toggleFull()" class="hover:text-white" x-text="full ? 'Zmenšit' : 'Celá obrazovka'"></button>
                    </div>

                    <div x-show="state === 'none'" x-cloak
                         class="absolute inset-0 flex items-center justify-center p-6 text-center text-sm text-gray-400">
                        Žádná konverzace neběží. Založte novou, nebo probuďte některou ze seznamu níž.
                    </div>
                </div>
            </div>

            <x-filament::section>
                <x-slot name="heading">Konverzace</x-slot>
                <x-slot name="description">Běžící konverzace jde otevřít, uspanou probudit. Nepřipnutá konverzace se po 12 hodinách bez pohybu sama uspí. Připnutá běží pořád a je dostupná i v aplikaci Claude na mobilu.</x-slot>

                <div class="-mx-6 -my-6 divide-y divide-gray-950/5 dark:divide-white/10">
                    @forelse ($this->conversationItems() as $item)
                        <div wire:key="konverzace-{{ $item['session'] ?? $item['okno'] }}"
                             @class([
                                 'flex flex-wrap items-center gap-x-4 gap-y-2 px-6 py-3',
                                 'bg-primary-50 dark:bg-primary-500/10' => $item['selected'],
                             ])>
                            <div class="min-w-0 grow basis-64">
                                <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $item['nazev'] }}</p>
                                @if ($item['meta'])
                                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $item['meta'] }}</p>
                                @endif
                            </div>

                            <x-filament::badge :color="$item['status']['color']">{{ $item['status']['label'] }}</x-filament::badge>

                            <div class="flex items-center gap-2">
                                @if ($item['selected'])
                                    <span class="px-2 text-xs font-semibold text-primary-600 dark:text-primary-400">Otevřená</span>
                                @elseif ($item['okno'])
                                    <x-filament::button size="xs" color="gray" wire:click="select('{{ $item['okno'] }}')">Otevřít</x-filament::button>
                                @else
                                    <x-filament::button size="xs" color="gray" wire:click="select(null, '{{ $item['session'] }}')">Probudit</x-filament::button>
                                @endif

                                @if ($item['okno'])
                                    <x-filament::button size="xs" color="gray" outlined
                                        :icon="$item['trvala'] ? 'heroicon-m-bookmark-slash' : 'heroicon-m-bookmark'"
                                        wire:click="togglePin('{{ $item['okno'] }}')">
                                        {{ $item['trvala'] ? 'Odepnout' : 'Připnout' }}
                                    </x-filament::button>
                                    <x-filament::button size="xs" color="gray" outlined wire:click="mountAction('sleepWindow', { window: '{{ $item['okno'] }}' })">Uspat</x-filament::button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">Zatím žádná konverzace.</p>
                    @endforelse
                </div>
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
