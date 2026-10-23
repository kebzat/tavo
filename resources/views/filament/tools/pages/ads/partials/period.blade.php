{{-- Volba období, srovnání a stav dat. Logika je v HasAdsPeriod a DataStatus. --}}
<div class="flex flex-col gap-2">
    <div class="flex flex-wrap items-center gap-2" x-data="{ custom: @js($this->period === 'custom') }">
        @foreach ($this->periods() as $key => $label)
            <x-filament::button size="sm" :color="$this->period === $key ? 'primary' : 'gray'" wire:click="setPeriod('{{ $key }}')">
                {{ $label }}
            </x-filament::button>
        @endforeach

        <x-filament::button size="sm" :color="$this->period === 'custom' ? 'primary' : 'gray'" icon="heroicon-m-calendar-days" x-on:click="custom = ! custom">
            Vlastní období
        </x-filament::button>

        <form x-show="custom" x-cloak wire:submit="applyRange" class="flex flex-wrap items-center gap-2">
            <x-filament::input.wrapper>
                <x-filament::input type="date" wire:model="from" :min="$this->minDate()" :max="$this->maxDate()" aria-label="Od" />
            </x-filament::input.wrapper>
            <span class="text-sm text-gray-500">až</span>
            <x-filament::input.wrapper>
                <x-filament::input type="date" wire:model="to" :min="$this->minDate()" :max="$this->maxDate()" aria-label="Do" />
            </x-filament::input.wrapper>
            <x-filament::button size="sm" type="submit">Použít</x-filament::button>
        </form>
    </div>

    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
        <span>Porovnat:</span>
        @foreach ($this->comparisons() as $key => $label)
            <button type="button" wire:click="setCompare('{{ $key }}')" @class([
                'rounded-md px-2 py-0.5',
                'bg-gray-100 font-semibold text-gray-950 dark:bg-white/10 dark:text-white' => $this->compare === $key,
                'hover:text-gray-950 dark:hover:text-white' => $this->compare !== $key,
            ])>{{ $label }}</button>
        @endforeach
        <span class="text-gray-400">({{ $this->comparisonPeriod()->label() }})</span>
    </div>

    @if ($note)
        <p class="text-sm text-warning-600 dark:text-warning-400">{{ $note }}</p>
    @endif
</div>
