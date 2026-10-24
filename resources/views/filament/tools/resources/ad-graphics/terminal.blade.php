{{--
    Terminál konverzace agenta u posledního kola. Během práce (running) jen ke
    sledování, Zasáhnout ho přepne na psaní; úloha ve frontě ještě okno nemá,
    zkouší se znovu. Po doběhnutí rovnou na psaní (pokračovat s kontextem),
    uspanou konverzaci jde probudit. wire:ignore, ať ho obnova stránky nepřekreslí.
--}}
<div wire:ignore wire:key="terminal-{{ $round->getKey() }}-{{ $running ? 'prace' : 'pokracovani' }}"
     @class(['overflow-hidden rounded-xl bg-terminal shadow-sm ring-1 ring-gray-950/10', 'mt-4' => $running, 'mt-6' => ! $running])
     x-data="{
         running: @js($running),
         state: 'connecting',
         full: false,
         seen: false,
         write: @js(! $running),
         waking: false,
         term: null,
         init() {
             const start = () => {
                 this.term = new window.TavoAgentTerminal(this.$refs.screen, {
                     url: () => this.$wire.terminalUrl(this.write),
                     onState: (state) => {
                         this.state = state;
                         if (state === 'open') this.seen = true;
                         if (! this.$el.isConnected || ! this.running) return;
                         // Během práce: okno ještě není (fronta) nebo agent dopsal, zkusit znovu a obnovit stránku.
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
         async wake() {
             this.waking = true;
             await this.$wire.wake();
             this.waking = false;
             this.term?.connect();
         },
     }"
     x-bind:class="full && 'fixed inset-0 z-50 rounded-none'">
    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 border-b border-white/10 px-4 py-2.5">
        <p class="text-sm font-semibold text-white">{{ $running ? 'Co agent dělá' : 'Pokračovat s agentem' }}</p>
        <div class="flex items-center gap-3 whitespace-nowrap text-xs text-gray-400">
            <span class="hidden sm:inline" x-text="{
                connecting: 'Připojuji…',
                open: write ? 'Píšete agentovi' : 'Živě, jen sledujete',
                reconnecting: 'Spojení spadlo, připojuji znovu…',
                closed: running ? 'Odpojeno, zkouším znovu…' : 'Odpojeno',
                none: running ? (seen ? 'Agent dopsal, načítám návrhy…' : 'Agent se chystá…') : 'Konverzace spí',
            }[state] ?? ''"></span>
            <button type="button" x-on:click="toggleFull()" class="hover:text-white" x-text="full ? 'Zmenšit' : 'Celá obrazovka'"></button>
            @if ($running)
                <x-filament::button size="xs" x-on:click="toggleWrite()" x-show="! write" icon="heroicon-m-hand-raised">Zasáhnout</x-filament::button>
                <x-filament::button size="xs" color="gray" x-on:click="toggleWrite()" x-show="write" x-cloak icon="heroicon-m-eye">Jen sledovat</x-filament::button>
            @endif
        </div>
    </div>

    <p x-show="write" x-cloak class="border-b border-white/10 bg-primary-500/15 px-4 py-2 text-xs text-gray-200">
        @if ($running)
            Píšete přímo agentovi. Klávesou Esc ho zastavíte, pak napište, co má udělat jinak, a odešlete Enterem.
            Úloha skončí, až agent odevzdá návrhy, na každou vaši zprávu má navíc 15 minut.
        @else
            Agent má celý kontext zadání. Napište, co změnit (třeba „u dvojky větší cenu“) a odešlete Enterem.
            Upraví PNG i Figmu a nové návrhy se ukážou v galerii výš.
        @endif
    </p>

    <div class="relative px-2 pb-2 pt-1">
        <div x-ref="screen" x-bind:class="full ? 'h-[calc(100vh-3.5rem)]' : 'h-[32rem]'"></div>

        <div x-show="state === 'none' && (! running || ! seen)" x-cloak
             class="absolute inset-0 flex flex-col items-center justify-center gap-3 p-6 text-center text-sm text-gray-400">
            @if ($running)
                Agent se ještě nerozběhl (úloha může čekat, než doběhne jiná v tomtéž projektu). Terminál se připojí sám.
            @else
                <span>Konverzace spí. Po probuzení v ní agent pokračuje se vším, co k zadání ví.</span>
                <x-filament::button size="sm" x-on:click="wake()" x-bind:disabled="waking" icon="heroicon-m-play">Probudit konverzaci</x-filament::button>
            @endif
        </div>
    </div>
</div>
