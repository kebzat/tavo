{{--
    Řádek firmy v přehledu „Dnes". Na mobilu se skládá pod sebe, na širší
    obrazovce jde text vlevo a tlačítka vpravo.

    $company  — firma i s načtenou poslední aktivitou
    $overdue  — kreslit červeně (blok „po termínu")
--}}
@php($activity = $company->activities->first())

<div @class([
    'flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-start sm:justify-between',
    'border-danger-300 bg-danger-50 dark:border-danger-500/40 dark:bg-danger-500/10' => $overdue ?? false,
    'border-gray-200 bg-white dark:border-white/10 dark:bg-white/5' => ! ($overdue ?? false),
])>
    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
            <a
                href="{{ \App\Filament\Tools\Resources\Companies\CompanyResource::getUrl('edit', ['record' => $company]) }}"
                class="font-semibold text-gray-950 hover:underline dark:text-white"
            >
                {{ $company->name }}
            </a>

            <x-filament::badge :color="$company->priority->getColor()" size="sm">
                {{ $company->priority->short() }}
            </x-filament::badge>

            <x-filament::badge :color="$company->status->getColor()" size="sm">
                {{ $company->status->getLabel() }}
            </x-filament::badge>

            @if ($company->owner)
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $company->owner->name }}</span>
            @endif
        </div>

        @if ($activity)
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                <span class="font-medium">{{ $activity->type->getLabel() }}</span>
                <span aria-hidden="true">·</span>
                {{ $activity->happened_at->format('j. n. Y') }}
                @if ($activity->excerpt(90))
                    <span aria-hidden="true">·</span> {{ $activity->excerpt(90) }}
                @endif
            </p>
        @else
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Zatím bez jediné aktivity.</p>
        @endif

        @if ($company->next_action_at)
            <p @class([
                'mt-1 text-xs',
                'font-semibold text-danger-600 dark:text-danger-400' => $overdue ?? false,
                'text-gray-500 dark:text-gray-400' => ! ($overdue ?? false),
            ])>
                Termín {{ $company->next_action_at->format('j. n. Y') }}
            </p>
        @endif
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <x-filament::button
            size="sm"
            icon="heroicon-o-check"
            wire:click="mountAction('logActivity', { company: {{ $company->getKey() }} })"
        >
            Hotovo
        </x-filament::button>

        @foreach ([3, 7] as $days)
            <x-filament::button
                size="sm"
                color="gray"
                wire:click="snooze({{ $company->getKey() }}, {{ $days }})"
                wire:loading.attr="disabled"
            >
                +{{ $days }} {{ \App\Filament\Tools\Actions\LogActivityAction::dayWord($days) }}
            </x-filament::button>
        @endforeach
    </div>
</div>
