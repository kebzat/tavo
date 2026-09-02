<x-filament-panels::page>
    @php
        $overdue = $this->overdue();
        $dueToday = $this->dueToday();
        $dueThisWeek = $this->dueThisWeek();
        $demands = $this->newDemands();
        $stale = $this->stale();
        $untouched = $this->untouched();
        $untouchedTotal = $this->untouchedTotal();
    @endphp

    <x-filament::section icon="heroicon-o-exclamation-circle" icon-color="danger">
        <x-slot name="heading">Po termínu ({{ $overdue->count() }})</x-slot>
        <x-slot name="description">Follow-up měl proběhnout a neproběhl.</x-slot>

        <div class="space-y-3">
            @forelse ($overdue as $company)
                @include('filament.tools.partials.company-row', ['company' => $company, 'overdue' => true])
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">Nic po termínu. Tak to má být.</p>
            @endforelse
        </div>
    </x-filament::section>

    <x-filament::section icon="heroicon-o-sun">
        <x-slot name="heading">Dnes ({{ $dueToday->count() }})</x-slot>

        <div class="space-y-3">
            @forelse ($dueToday as $company)
                @include('filament.tools.partials.company-row', ['company' => $company, 'overdue' => false])
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">Na dnešek nic naplánovaného.</p>
            @endforelse
        </div>
    </x-filament::section>

    <x-filament::section icon="heroicon-o-calendar-days" collapsible>
        <x-slot name="heading">Tento týden ({{ $dueThisWeek->count() }})</x-slot>
        <x-slot name="description">Od zítřka do neděle.</x-slot>

        <div class="space-y-3">
            @forelse ($dueThisWeek as $company)
                @include('filament.tools.partials.company-row', ['company' => $company, 'overdue' => false])
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">Do konce týdne nic nečeká.</p>
            @endforelse
        </div>
    </x-filament::section>

    <x-filament::section icon="heroicon-o-megaphone" collapsible>
        <x-slot name="heading">K oslovení ({{ $untouchedTotal }})</x-slot>
        <x-slot name="description">
            Firmy, kterým jsme se ještě neozvali. Nejdřív áčka.
            @if ($untouchedTotal > $untouched->count())
                Zobrazeno prvních {{ $untouched->count() }}.
            @endif
        </x-slot>

        <div class="space-y-3">
            @forelse ($untouched as $company)
                @include('filament.tools.partials.company-row', ['company' => $company, 'overdue' => false])
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Všechny firmy už jsou oslovené. Naimportuj další rešerši přes CRM → Import firem.
                </p>
            @endforelse
        </div>

        @if ($untouchedTotal > $untouched->count())
            <div class="mt-4">
                <x-filament::button
                    size="sm"
                    color="gray"
                    tag="a"
                    :href="\App\Filament\Tools\Resources\Companies\CompanyResource::getUrl('index')"
                >
                    Zobrazit všech {{ $untouchedTotal }}
                </x-filament::button>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section icon="heroicon-o-inbox-arrow-down" icon-color="warning">
        <x-slot name="heading">Nové poptávky ({{ $demands->count() }})</x-slot>
        <x-slot name="description">Z portálů, zatím bez reakce.</x-slot>

        <div class="space-y-3">
            @forelse ($demands as $demand)
                <div class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 sm:flex-row sm:items-start sm:justify-between dark:border-white/10 dark:bg-white/5">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ $demand->url }}" target="_blank" rel="noopener"
                               class="font-semibold text-gray-950 hover:underline dark:text-white">
                                {{ $demand->title }}
                            </a>

                            <x-filament::badge :color="$demand->priority->getColor()" size="sm">
                                {{ $demand->priority->short() }}
                            </x-filament::badge>

                            <x-filament::badge color="gray" size="sm">{{ $demand->source->getLabel() }}</x-filament::badge>
                        </div>

                        @if ($demand->summary)
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ \Illuminate\Support\Str::limit($demand->summary, 140) }}</p>
                        @endif

                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ $demand->posted_at?->format('j. n. Y') ?? 'bez data' }}
                            @if ($demand->budget_estimate)
                                <span aria-hidden="true">·</span> {{ $demand->budget_estimate }}
                            @endif
                        </p>
                    </div>

                    <x-filament::button
                        size="sm"
                        color="gray"
                        tag="a"
                        :href="\App\Filament\Tools\Resources\Demands\DemandResource::getUrl('index')"
                    >
                        Vyřídit
                    </x-filament::button>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">Žádné nové poptávky. Ranní import je doplní sám.</p>
            @endforelse
        </div>
    </x-filament::section>

    <x-filament::section icon="heroicon-o-moon" collapsible collapsed>
        <x-slot name="heading">Bez pohybu ({{ $stale->count() }})</x-slot>
        <x-slot name="description">Rozjednané firmy, u kterých se týden nic nestalo.</x-slot>

        <div class="space-y-3">
            @forelse ($stale as $company)
                @include('filament.tools.partials.company-row', ['company' => $company, 'overdue' => false])
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">Nic nezapadlo.</p>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-panels::page>
