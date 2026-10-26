{{-- Mozek TAVEO: co v něm je, otázka na mozek a předchozí rozhovor. Během odpovědi se obnovuje sama. --}}
<x-filament-panels::page>
    @vite('resources/js/agent-terminal.js')

    <div @if ($this->running) wire:poll.10s @endif class="space-y-6">
        @if ($this->ready === false)
            <div class="rounded-xl bg-warning-50 px-5 py-4 text-sm text-warning-800 ring-1 ring-warning-600/10 dark:bg-warning-500/10 dark:text-warning-300">
                Mozek ještě nemá u agenta svůj projekt. Klikněte nahoře na <strong>Připravit mozek</strong>, stačí jednou.
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-3">
            <a href="{{ $this->entriesUrl('plati') }}" class="rounded-xl bg-white p-5 ring-1 ring-gray-950/5 transition hover:ring-primary-500/40 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-sm text-gray-500 dark:text-gray-400">Platí poznatků</p>
                <p class="mt-1 text-3xl font-semibold text-gray-950 dark:text-white">{{ $this->stats['approved'] }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    firma {{ $this->layers['firma'] ?? 0 }} · klienti {{ $this->layers['klient'] ?? 0 }} · postupy {{ $this->layers['postup'] ?? 0 }}
                </p>
            </a>
            <a href="{{ $this->entriesUrl('navrhy') }}" class="rounded-xl bg-white p-5 ring-1 ring-gray-950/5 transition hover:ring-primary-500/40 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-sm text-gray-500 dark:text-gray-400">Ke schválení</p>
                <p @class(['mt-1 text-3xl font-semibold', 'text-warning-600 dark:text-warning-400' => $this->stats['proposed'] > 0, 'text-gray-950 dark:text-white' => $this->stats['proposed'] === 0])>{{ $this->stats['proposed'] }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">návrhy od agentů, platí až po schválení</p>
            </a>
            <a href="{{ \App\Filament\Tools\Resources\KnowledgeSources\KnowledgeSourceResource::getUrl('index') }}" class="rounded-xl bg-white p-5 ring-1 ring-gray-950/5 transition hover:ring-primary-500/40 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-sm text-gray-500 dark:text-gray-400">Nahrané podklady</p>
                <p class="mt-1 text-3xl font-semibold text-gray-950 dark:text-white">{{ $this->stats['sources'] }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ $this->stats['running'] > 0 ? 'agent právě čte '.$this->stats['running'] : 'texty, poznámky, e-maily' }}
                </p>
            </a>
        </div>

        <x-filament::section icon="heroicon-o-chat-bubble-left-right">
            <x-slot name="heading">Zeptat se mozku</x-slot>
            <x-slot name="description">
                Mozek hledá ve schválených poznatcích a u odpovědi napíše, odkud ji má. Navazuje na předchozí otázky.
                Když mu napíšete „zapamatuj si…“, navrhne nový poznatek.
            </x-slot>

            <form wire:submit="ask" class="space-y-3">
                <x-filament::input.wrapper :valid="! $errors->has('question')">
                    <textarea wire:model="question" rows="3" maxlength="5000"
                              placeholder="Co víme o tom, jak Svět Cejlonu chce reklamy? / Jak děláme měsíční report? / Zapamatuj si, že…"
                              class="block w-full resize-y border-none bg-transparent px-3 py-2 text-sm text-gray-950 placeholder:text-gray-400 focus:ring-0 dark:text-white"
                              @disabled($this->running)></textarea>
                </x-filament::input.wrapper>
                @error('question')
                    <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                @enderror

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                        <x-filament::input.checkbox wire:model="fresh" :disabled="! $this->questions->count()" />
                        Začít nový rozhovor (mozek zapomene předchozí otázky, poznatky zůstanou)
                    </label>
                    <x-filament::button type="submit" icon="heroicon-m-paper-airplane" :disabled="(bool) $this->running">Zeptat se</x-filament::button>
                </div>
            </form>

            @if ($this->running)
                <div class="mt-6 rounded-lg bg-gray-50 px-4 py-3 text-sm dark:bg-white/5">
                    <p class="font-semibold text-gray-950 dark:text-white">{{ $this->running->author }}: {{ $this->running->instruction }}</p>
                    <p class="mt-1 flex items-center gap-2 text-gray-500 dark:text-gray-400">
                        <x-filament::loading-indicator class="h-4 w-4" /> Mozek hledá a píše odpověď, obvykle do minuty nebo dvou.
                    </p>
                </div>

                @include('filament.tools.partials.agent-terminal', ['key' => 'dotaz-'.$this->running->getKey(), 'done' => 'Mozek dopsal, načítám odpověď…'])
            @endif
        </x-filament::section>

        @foreach ($this->questions->reject(fn ($job) => $job->is($this->running)) as $job)
            <x-filament::section wire:key="dotaz-{{ $job->getKey() }}" compact>
                <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $job->instruction }}</p>
                <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">{{ $job->author }}, {{ $job->created_at->format('j. n. H:i') }}</p>

                @if ($job->status === \App\Models\AgentJob::STATUS_FAILED)
                    <div class="rounded-lg bg-danger-50 px-4 py-3 text-sm text-danger-700 dark:bg-danger-500/10 dark:text-danger-300">
                        Nepovedlo se: {{ $job->error }}
                    </div>
                @endif

                @if ($this->answer($job))
                    <div class="ads-advice text-sm leading-relaxed text-gray-700 dark:text-gray-300">{{ $this->answer($job) }}</div>
                @endif
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
