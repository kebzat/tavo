{{-- Využití předplatného Claude uprostřed hlavičky nástrojů. Viz App\Filament\Tools\Livewire\ClaudeUsageMeter. --}}
<div wire:poll.120s.visible class="claude-usage">
    <x-filament::dropdown placement="bottom" width="xs" teleport>
        <x-slot name="trigger">
            <button
                type="button"
                title="{{ $usage->error ?? 'Předplatné Claude: využití limitů' }}"
                class="flex items-center gap-2.5 rounded-full bg-gray-50 px-3 py-1.5 ring-1 ring-gray-950/5 transition hover:bg-gray-100 sm:gap-3.5 sm:ps-3 sm:pe-4 dark:bg-white/5 dark:ring-white/10 dark:hover:bg-white/10"
            >
                <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::Sparkles" class="hidden size-4 shrink-0 text-primary-600 sm:block dark:text-primary-400" />

                @forelse ($usage->headline() as $limit)
                    @unless ($loop->first)
                        <span class="h-3.5 w-px shrink-0 bg-gray-200 dark:bg-white/10"></span>
                    @endunless

                    {{-- Na telefonu jen procento a pod ním tenký pruh, popisky jsou v rozbalení. --}}
                    <span class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-2" title="{{ $limit->label() }}">
                        <span class="hidden text-xs whitespace-nowrap text-gray-500 sm:inline dark:text-gray-400">{{ $limit->shortLabel() }}</span>
                        <span class="h-1 w-full shrink-0 overflow-hidden rounded-full bg-gray-200 sm:h-1.5 sm:w-14 lg:w-20 dark:bg-white/10">
                            <span
                                @class([
                                    'block h-full rounded-full transition-[width] duration-700',
                                    'bg-success-600 dark:bg-success-500' => $limit->level() === 'ok',
                                    'bg-warning-500' => $limit->level() === 'warning',
                                    'bg-danger-600 dark:bg-danger-500' => $limit->level() === 'danger',
                                ])
                                style="width: {{ $limit->bar() }}%"
                            ></span>
                        </span>
                        <span
                            @class([
                                '-order-1 text-xs leading-none font-semibold whitespace-nowrap tabular-nums sm:order-none sm:leading-normal',
                                'text-gray-900 dark:text-white' => $limit->level() !== 'danger',
                                'text-danger-600 dark:text-danger-400' => $limit->level() === 'danger',
                            ])
                        >{{ $limit->percent }} %</span>
                    </span>
                @empty
                    <span class="text-xs text-gray-500 dark:text-gray-400">Claude –</span>
                @endforelse
            </button>
        </x-slot>

        <div class="space-y-4 p-4">
            <div class="flex items-baseline justify-between gap-3">
                <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $usage->planLabel() ?? 'Předplatné Claude' }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">agent na serveru</p>
            </div>

            @if ($usage->error)
                <p class="text-sm text-gray-600 dark:text-gray-300">{{ $usage->error }}</p>
            @endif

            @foreach ($usage->limits as $limit)
                <div>
                    <div class="flex items-baseline justify-between gap-3 text-sm">
                        <span class="font-medium text-gray-700 dark:text-gray-200">{{ $limit->label() }}</span>
                        <span
                            @class([
                                'font-semibold tabular-nums',
                                'text-gray-950 dark:text-white' => $limit->level() !== 'danger',
                                'text-danger-600 dark:text-danger-400' => $limit->level() === 'danger',
                            ])
                        >{{ $limit->percent }} %</span>
                    </div>

                    <div class="relative mt-1.5 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                        <span
                            @class([
                                'block h-full rounded-full',
                                'bg-success-600 dark:bg-success-500' => $limit->level() === 'ok',
                                'bg-warning-500' => $limit->level() === 'warning',
                                'bg-danger-600 dark:bg-danger-500' => $limit->level() === 'danger',
                            ])
                            style="width: {{ $limit->bar() }}%"
                        ></span>
                        @if ($limit->elapsed() !== null)
                            <span class="absolute inset-y-0 w-0.5 bg-gray-950/40 dark:bg-white/50" style="left: {{ $limit->elapsed() }}%"></span>
                        @endif
                    </div>

                    @if ($limit->resetLabel())
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Obnoví se {{ $limit->resetLabel() }}</p>
                    @endif
                </div>
            @endforeach

            @if ($usage->limits)
                <p class="border-t border-gray-100 pt-3 text-xs leading-relaxed text-gray-500 dark:border-white/10 dark:text-gray-400">
                    Ryska ukazuje, kolik z okna už uběhlo. Když je pruh delší, limit dojde dřív, než se obnoví.
                </p>
            @endif
        </div>
    </x-filament::dropdown>
</div>
