{{-- Hotová práce agenta podle typu projektu. Viz App\Filament\Tools\Livewire\AgentHandovers. --}}
<div @if ($running) wire:poll.10s @else wire:poll.30s @endif>
    <x-filament::section :icon="$copy['icon']" icon-color="primary">
        <x-slot name="heading">{{ $copy['heading'] }}</x-slot>
        <x-slot name="description">{{ $copy['description'] }}</x-slot>

        <div class="-mx-6 -my-6 divide-y divide-gray-950/5 dark:divide-white/10">
            @unless ($configured)
                <p class="bg-warning-50 px-6 py-3 text-sm text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">
                    Zveřejnění ještě není zapnuté: na serveru chybí <code>GITHUB_TOKEN</code>. Nastaví ho Tom, návod je v docs/AGENT.md (Zveřejnění).
                </p>
            @endunless

            @if ($error)
                <p class="px-6 py-3 text-sm text-danger-600 dark:text-danger-400">{{ $error }}</p>
            @endif

            {{-- Průběh zveřejnění: testy → nasazení → na webu. --}}
            @foreach ($active as $publication)
                <div wire:key="zverejneni-{{ $publication->id }}" class="flex flex-wrap items-center gap-x-6 gap-y-3 px-6 py-4">
                    <div class="min-w-0 grow basis-64">
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $publication->title }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $publication->isRevert() || $publication->isRestore() ? 'Vrací' : 'Zveřejňuje' }} {{ $publication->user?->name ?? 'neznámý' }} · {{ $publication->created_at->diffForHumans() }}
                            @if ($publication->isRestore() && $publication->description) · {{ $publication->description }} @endif
                        </p>
                    </div>

                    @if ($publication->isRestore() && $publication->status->isRunning())
                        <span class="flex items-center gap-1.5 text-xs font-semibold text-gray-950 dark:text-white">
                            <x-filament::loading-indicator class="size-5 text-primary-600 dark:text-primary-400" />
                            Vrací se
                        </span>
                    @elseif (! $publication->isRestore() && ($publication->status->isRunning() || $publication->status === \App\Enums\PublicationStatus::Done))
                        <ol class="flex items-center gap-2 text-xs">
                            @foreach ([1 => 'Testy', 2 => 'Nasazení', 3 => 'Na webu'] as $step => $label)
                                @unless ($loop->first)
                                    <li aria-hidden="true" @class([
                                        'h-px w-4 sm:w-8',
                                        'bg-success-500' => $publication->status->stepState($step) !== 'todo',
                                        'bg-gray-200 dark:bg-white/10' => $publication->status->stepState($step) === 'todo',
                                    ])></li>
                                @endunless
                                <li class="flex items-center gap-1.5">
                                    @switch ($publication->status->stepState($step))
                                        @case ('done')
                                            <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::CheckCircle" class="size-5 text-success-600 dark:text-success-400" />
                                            <span class="font-medium text-gray-700 dark:text-gray-200">{{ $label }}</span>
                                            @break
                                        @case ('current')
                                            <x-filament::loading-indicator class="size-5 text-primary-600 dark:text-primary-400" />
                                            <span class="font-semibold text-gray-950 dark:text-white">{{ $label }}</span>
                                            @break
                                        @default
                                            <span class="size-5 rounded-full border-2 border-gray-200 dark:border-white/15"></span>
                                            <span class="text-gray-400 dark:text-gray-500">{{ $label }}</span>
                                    @endswitch
                                </li>
                            @endforeach
                        </ol>
                    @else
                        <x-filament::badge :color="$publication->status->getColor()">{{ $publication->status->getLabel() }}</x-filament::badge>
                    @endif

                    @if ($revertable?->is($publication))
                        {{ ($this->revertAction)(['id' => $publication->id]) }}
                    @endif

                    @if ($publication->error)
                        <p class="basis-full text-sm text-danger-600 dark:text-danger-400">
                            {{ $publication->error }}
                            @if ($publication->details_url)
                                <a href="{{ $publication->details_url }}" target="_blank" rel="noopener" class="font-semibold underline">Podrobnosti na GitHubu</a>
                            @endif
                        </p>
                    @endif
                </div>
            @endforeach

            {{-- Hotová práce, která čeká na kontrolu. --}}
            @foreach ($handovers as $item)
                <div wire:key="predani-{{ $item['name'] }}-{{ $item['sha'] }}" class="flex flex-wrap items-center gap-x-4 gap-y-3 px-6 py-4">
                    @if ($item['images'])
                        <button type="button" wire:click="mountAction('review', { name: @js($item['name']), sha: @js($item['sha']) })"
                                class="hidden h-14 w-24 shrink-0 overflow-hidden rounded-lg bg-gray-100 ring-1 ring-gray-950/10 transition hover:ring-primary-500 sm:block dark:bg-white/5 dark:ring-white/10">
                            <img src="{{ $this->imageUrl($item['name'], $item['images'][0]) }}" alt="" loading="lazy" class="size-full object-cover object-top">
                        </button>
                    @endif

                    <div class="min-w-0 grow basis-56">
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $item['title'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ \Illuminate\Support\Carbon::createFromTimestamp($item['time'])->diffForHumans() }}
                            @if ($item['images']) · {{ $this->plural(count($item['images']), 'snímek', 'snímky', 'snímků') }} @endif
                            @if ($item['codes']) · {{ $this->plural(count($item['codes']), 'kód k vložení', 'kódy k vložení', 'kódů k vložení') }} @endif
                            @if ($item['files']) · {{ $this->plural($item['files'], 'změněný soubor', 'změněné soubory', 'změněných souborů') }} @endif
                        </p>
                        @if (! $item['on_github'] || $item['sensitive'] || ! ($item['images'] || $item['description'] || $item['codes']))
                            <div class="mt-1.5 flex flex-wrap gap-1.5">
                                @unless ($item['on_github'])
                                    <x-filament::badge color="gray" size="sm">Odesílá se na GitHub…</x-filament::badge>
                                @endunless
                                @if ($item['sensitive'])
                                    <x-filament::badge color="warning" size="sm" icon="heroicon-m-exclamation-triangle">Mění nastavení serveru</x-filament::badge>
                                @endif
                                @unless ($item['images'] || $item['description'] || $item['codes'])
                                    <x-filament::badge color="gray" size="sm">Bez snímků a popisu</x-filament::badge>
                                @endunless
                            </div>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        @foreach ($this->rowActions($item) as $action)
                            {{ $action }}
                        @endforeach
                    </div>
                </div>
            @endforeach

            @if ($handovers === [] && $active->isEmpty() && ! $error)
                <p class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $copy['empty'] }}</p>
            @endif

            @if ($history->isNotEmpty())
                <div class="bg-gray-50 px-6 py-3 dark:bg-white/5">
                    <p class="mb-2 text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">{{ $copy['history'] }}</p>
                    <ul class="space-y-1.5">
                        @foreach ($history as $publication)
                            <li wire:key="historie-{{ $publication->id }}" class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                                <span class="min-w-0 truncate text-gray-700 dark:text-gray-200">{{ $publication->title }}</span>
                                <x-filament::badge :color="$publication->status->getColor()" size="sm">{{ $publication->status->getLabel() }}</x-filament::badge>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $publication->user?->name }} · {{ $publication->created_at->diffForHumans() }}</span>
                                @if ($revertable?->is($publication))
                                    <span class="ms-auto">{{ ($this->revertAction)(['id' => $publication->id]) }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </x-filament::section>

    <x-filament-actions::modals />
</div>
