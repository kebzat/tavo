<x-filament-panels::page>
    @php($columns = $this->columns())

    {{--
        Sloupce se vodorovně posouvají. Na šířku telefonu se jich sedm vedle
        sebe nevejde a lámat je pod sebe by z kanbanu udělalo dlouhý seznam.
    --}}
    <div class="-mx-4 overflow-x-auto px-4 pb-4 sm:mx-0 sm:px-0">
        <div class="flex min-w-max gap-4">
            @foreach ($columns as $column)
                <div
                    class="flex w-72 shrink-0 flex-col rounded-xl bg-gray-100 p-3 dark:bg-white/5"
                    x-data="{ over: false }"
                    x-bind:class="over && 'ring-2 ring-primary-500'"
                    x-on:dragover.prevent="over = true"
                    x-on:dragleave="over = false"
                    x-on:drop.prevent="
                        over = false;
                        $wire.moveDeal(Number($event.dataTransfer.getData('text/plain')), '{{ $column['stage']->value }}')
                    "
                >
                    <div class="mb-3 px-1">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-semibold text-gray-950 dark:text-white">
                                {{ $column['stage']->getLabel() }}
                            </span>
                            <x-filament::badge :color="$column['stage']->getColor()" size="sm">
                                {{ $column['deals']->count() }}
                            </x-filament::badge>
                        </div>

                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ number_format($column['total'], 0, ',', ' ') }} Kč
                            <span aria-hidden="true">·</span>
                            vážená {{ number_format($column['weighted'], 0, ',', ' ') }} Kč
                        </p>
                    </div>

                    <div class="space-y-2">
                        @forelse ($column['deals'] as $deal)
                            <div
                                class="cursor-grab rounded-lg border border-gray-200 bg-white p-3 shadow-sm active:cursor-grabbing dark:border-white/10 dark:bg-gray-900"
                                draggable="true"
                                x-on:dragstart="$event.dataTransfer.setData('text/plain', '{{ $deal->getKey() }}')"
                            >
                                <a
                                    href="{{ \App\Filament\Tools\Resources\Companies\CompanyResource::getUrl('edit', ['record' => $deal->company_id]) }}"
                                    class="block text-sm font-semibold text-gray-950 hover:underline dark:text-white"
                                >
                                    {{ $deal->company?->name }}
                                </a>

                                <p class="mt-0.5 text-xs text-gray-600 dark:text-gray-400">{{ $deal->title }}</p>

                                <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                    @if ($deal->value_czk)
                                        <span class="font-medium text-gray-950 dark:text-white">
                                            {{ number_format($deal->value_czk, 0, ',', ' ') }} Kč
                                        </span>
                                    @endif

                                    @if ($deal->owner)
                                        <span>{{ $deal->owner->name }}</span>
                                    @endif

                                    <span @class(['font-medium text-warning-600 dark:text-warning-400' => $deal->daysInStage() > 14])>
                                        {{ $deal->daysInStage() }} dní ve fázi
                                    </span>
                                </div>

                                {{-- Záchranná cesta pro dotyk, kde tažení nefunguje. --}}
                                <select
                                    class="mt-2 w-full rounded-md border-gray-300 bg-white py-1 text-xs text-gray-950 dark:border-white/10 dark:bg-gray-900 dark:text-white"
                                    wire:change="moveDeal({{ $deal->getKey() }}, $event.target.value)"
                                    aria-label="Změnit fázi obchodu"
                                >
                                    @foreach ($this->stageOptions() as $value => $label)
                                        <option value="{{ $value }}" @selected($deal->stage->value === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @empty
                            <p class="px-1 py-4 text-xs text-gray-500 dark:text-gray-400">Prázdné</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    @if ($columns->sum(fn ($column) => $column['deals']->count()) === 0)
        <x-filament::section>
            <x-slot name="heading">Zatím žádné obchody</x-slot>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Obchod se zakládá na kartě firmy, jakmile je z oslovení konkrétní příležitost.
                Objeví se tady a dá se táhnout mezi fázemi.
            </p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
