{{-- Terminál úlohy agenta na pozadí (mozek): výchozí jen ke sledování, Zasáhnout ho přepne
     na psaní. Adresu dává $wire.terminalUrl(write). wire:ignore, ať ho obnova stránky
     (wire:poll) nepřekreslí. Úloha ve frontě ještě okno nemá: zkouší se znovu.
     Parametry: $key (jedinečný klíč úlohy), $done (text, když agent dopsal). --}}
<div wire:ignore wire:key="terminal-{{ $key }}"
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
                         if (! this.$el.isConnected) return;
                         // Okno zmizelo (agent doběhl): rovnou ukázat výsledek.
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
                none: seen ? @js($done) : 'Agent se chystá…',
            }[state] ?? ''"></span>
            <button type="button" x-on:click="toggleFull()" class="hover:text-white" x-text="full ? 'Zmenšit' : 'Celá obrazovka'"></button>
            <x-filament::button size="xs" x-on:click="toggleWrite()" x-show="! write" icon="heroicon-m-hand-raised">Zasáhnout</x-filament::button>
            <x-filament::button size="xs" color="gray" x-on:click="toggleWrite()" x-show="write" x-cloak icon="heroicon-m-eye">Jen sledovat</x-filament::button>
        </div>
    </div>

    <p x-show="write" x-cloak class="border-b border-white/10 bg-primary-500/15 px-4 py-2 text-xs text-gray-200">
        Píšete přímo agentovi. Klávesou Esc ho zastavíte, pak napište, co má udělat jinak, a odešlete Enterem.
        Úloha skončí, až agent odevzdá výsledek, na každou vaši zprávu má navíc 15 minut.
    </p>

    <div class="relative px-2 pb-2 pt-1">
        <div x-ref="screen" x-bind:class="full ? 'h-[calc(100vh-3.5rem)]' : 'h-[32rem]'"></div>

        <div x-show="state === 'none' && ! seen" x-cloak
             class="absolute inset-0 flex items-center justify-center p-6 text-center text-sm text-gray-400">
            Agent se ještě nerozběhl (úloha může čekat, než doběhne jiná v mozku). Terminál se připojí sám.
        </div>
    </div>
</div>
