<x-filament-panels::page>
    @php($listing = $this->listing())
    @php($requests = $this->clientRequests())

    @if ($requests['queued']->isNotEmpty())
        <div wire:poll.5s>
            <x-filament::section icon="heroicon-o-arrow-path" icon-color="info" compact>
                <x-slot name="heading">Zakládají se agenti ({{ $requests['queued']->count() }})</x-slot>
                <x-slot name="description">Na pozadí po jednom, pár sekund na každého. Seznam se obnoví sám.</x-slot>
                <p class="text-sm text-gray-600 dark:text-gray-300">{{ $requests['queued']->pluck('name')->implode(', ') }}</p>
            </x-filament::section>
        </div>
    @endif

    @if ($requests['failed']->isNotEmpty())
        <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="warning" compact>
            <x-slot name="heading">Agent se nezaložil ({{ $requests['failed']->flatten()->count() }})</x-slot>
            <x-slot name="description">Klientům znovu tlačítkem Založit agenty klientům vpravo nahoře, skillu v jeho nastavení (Mozek → Skilly).</x-slot>
            <ul class="space-y-1 text-sm text-gray-600 dark:text-gray-300">
                @foreach ($requests['failed'] as $error => $clients)
                    <li><span class="font-medium text-gray-950 dark:text-white">{{ $clients->pluck('name')->implode(', ') }}</span>: {{ $error }}</li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif

    @if ($listing['all'] === 0 && ! $listing['error'])
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">Zatím žádný projekt. Přidejte ho tlačítkem vpravo nahoře.</p>
        </x-filament::section>
    @else
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass" class="w-full sm:w-72">
                <x-filament::input type="search" wire:model.live.debounce.150ms="search" wire:keydown.enter="openFirst"
                                   placeholder="Hledat projekt nebo skill" aria-label="Hledat projekt, klienta nebo skill" autocomplete="off"
                                   x-init="window.matchMedia('(min-width: 1024px)').matches && $el.focus()" />
            </x-filament::input.wrapper>

            @if (filled($search))
                <p class="text-sm text-gray-500 dark:text-gray-400">Nalezeno {{ $listing['found'] }} z {{ $listing['all'] }}</p>
            @endif
        </div>

        @if (filled($search) && $listing['found'] === 0)
            <x-filament::section>
                <p class="text-sm text-gray-500 dark:text-gray-400">Nic neodpovídá hledání „{{ $search }}“.</p>
            </x-filament::section>
        @endif

        @if ($listing['skills'] !== [])
            <section class="space-y-3" aria-labelledby="skilly">
                <div class="flex flex-wrap items-end justify-between gap-x-4 gap-y-1">
                    <div class="min-w-0">
                        <h2 id="skilly" class="text-base font-semibold text-gray-950 dark:text-white">Skilly</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Specialisté s vlastním know-how. Agenti klientů je volají (třeba <span class="font-mono">/meta-ads</span>) a učí se ze všech klientů.</p>
                    </div>
                    <x-filament::link :href="\App\Filament\Tools\Resources\Skills\SkillResource::getUrl()" icon="heroicon-m-adjustments-horizontal" size="sm" color="gray">Pokyny skillů</x-filament::link>
                </div>

                <div class="grid items-start gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($listing['skills'] as $category => $skills)
                        <div wire:key="skupina-{{ $category }}" class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                            <div class="flex items-center gap-2 border-b border-gray-950/5 px-4 py-3 dark:border-white/10">
                                <x-filament::icon :icon="\App\Models\Skill::icons()[$category] ?? 'heroicon-o-sparkles'" class="h-5 w-5 text-primary-500" />
                                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ \App\Models\Skill::categories()[$category] ?? $category }}</h3>
                            </div>

                            <div class="divide-y divide-gray-950/5 dark:divide-white/10">
                                @foreach ($skills as $skill)
                                    <a href="{{ $skill['url'] }}" wire:key="skill-{{ $skill['slug'] }}" class="group block px-4 py-3 transition hover:bg-gray-50 dark:hover:bg-white/5">
                                        <div class="flex items-baseline justify-between gap-3">
                                            <p class="min-w-0 font-bold text-gray-950 group-hover:text-primary-600 dark:text-white">{{ $skill['name'] }}</p>
                                            <span class="shrink-0 font-mono text-xs text-gray-400 dark:text-gray-500">/{{ $skill['slug'] }}</span>
                                        </div>
                                        <p class="mt-0.5 line-clamp-2 text-sm text-gray-600 dark:text-gray-300">{{ $skill['scope'] }}</p>
                                        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                            <span @class(['text-warning-600 dark:text-warning-400' => ! $skill['ready']])>{{ $skill['status'] }}</span>
                                            <span>{{ $skill['knowledge'] }}</span>
                                            @if ($skill['proposed'] > 0)
                                                <x-filament::badge color="warning" size="sm">{{ $skill['proposed'] }} ke schválení</x-filament::badge>
                                            @endif
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($listing['error'])
            <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="danger">
                <x-slot name="heading">Agent je nedostupný</x-slot>
                <p class="text-sm text-gray-600 dark:text-gray-300">{{ $listing['error'] }}</p>
            </x-filament::section>
        @endif

        @if ($listing['total'] > 0)
            <section class="space-y-3" aria-labelledby="klienti">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div class="min-w-0">
                        <h2 id="klienti" class="text-base font-semibold text-gray-950 dark:text-white">Klienti</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            @if ($listing['pages'] > 1)
                                {{ $listing['from'] }}–{{ $listing['to'] }} z {{ $listing['total'] }} projektů
                            @else
                                {{ $listing['total'] }} {{ match (true) { $listing['total'] === 1 => 'projekt', $listing['total'] < 5 => 'projekty', default => 'projektů' } }}, každý s mozkem a archivem klienta
                            @endif
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-500 dark:text-gray-400">Zobrazit</span>
                            <div class="inline-flex rounded-lg bg-gray-100 p-0.5 dark:bg-white/5">
                                @foreach (\App\Filament\Tools\Pages\Agent\AgentProjects::PER_PAGE as $value => $label)
                                    <button type="button" wire:click="$set('perPage', @js((string) $value))"
                                            @class([
                                                'rounded-md px-2.5 py-1 text-sm font-medium transition',
                                                'bg-white text-gray-950 shadow-sm dark:bg-gray-800 dark:text-white' => $perPage === (string) $value,
                                                'text-gray-500 hover:text-gray-950 dark:text-gray-400 dark:hover:text-white' => $perPage !== (string) $value,
                                            ])>{{ $label }}</button>
                                @endforeach
                            </div>
                        </div>

                        <div class="inline-flex rounded-lg bg-gray-100 p-0.5 dark:bg-white/5">
                            @foreach (['grid' => ['Mřížka', 'heroicon-m-squares-2x2'], 'list' => ['Seznam', 'heroicon-m-bars-3']] as $value => [$label, $icon])
                                <button type="button" wire:click="$set('display', @js($value))"
                                        @class([
                                            'inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-sm font-medium transition',
                                            'bg-white text-gray-950 shadow-sm dark:bg-gray-800 dark:text-white' => $display === $value,
                                            'text-gray-500 hover:text-gray-950 dark:text-gray-400 dark:hover:text-white' => $display !== $value,
                                        ])>
                                    <x-filament::icon :icon="$icon" class="h-4 w-4" />
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                @include('filament.tools.pages.agent.project-cards', ['projects' => $listing['projects'], 'group' => 'klient'])

                @if ($listing['pages'] > 1)
                    <nav class="flex flex-wrap items-center justify-center gap-1" aria-label="Stránky projektů">
                        <x-filament::button size="sm" color="gray" icon="heroicon-m-chevron-left" :disabled="$listing['page'] === 1" wire:click="goToPage({{ $listing['page'] - 1 }})">
                            Předchozí
                        </x-filament::button>
                        @for ($i = 1; $i <= $listing['pages']; $i++)
                            <x-filament::button size="sm" :color="$i === $listing['page'] ? 'primary' : 'gray'" wire:click="goToPage({{ $i }})">{{ $i }}</x-filament::button>
                        @endfor
                        <x-filament::button size="sm" color="gray" icon="heroicon-m-chevron-right" icon-position="after" :disabled="$listing['page'] === $listing['pages']" wire:click="goToPage({{ $listing['page'] + 1 }})">
                            Další
                        </x-filament::button>
                    </nav>
                @endif
            </section>
        @endif

        @if ($listing['internal'] !== [])
            <section class="space-y-3" aria-labelledby="interni">
                <div>
                    <h2 id="interni" class="text-base font-semibold text-gray-950 dark:text-white">Interní projekty</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Práce pro TAVEO samo: web a nástroje, marketing, mozek.</p>
                </div>

                @include('filament.tools.pages.agent.project-cards', ['projects' => $listing['internal'], 'group' => 'interni'])
            </section>
        @endif
    @endif

    @if ($output)
        <x-filament::section icon="heroicon-o-key" icon-color="warning">
            <x-slot name="heading">Co ještě udělat</x-slot>
            <x-slot name="description">Výstup z posledního přidání projektu. U repozitáře přidejte klíče na GitHubu a projekt přidejte znovu se stejným názvem.</x-slot>
            <pre class="overflow-x-auto rounded-lg bg-gray-950 p-4 text-xs leading-relaxed whitespace-pre-wrap text-gray-100">{{ $output }}</pre>
        </x-filament::section>
    @endif
</x-filament-panels::page>
