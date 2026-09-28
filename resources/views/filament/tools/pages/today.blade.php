<x-filament-panels::page>
    @php
        $overdue = $this->overdue();
        $dueToday = $this->dueToday();
        $dueThisWeek = $this->dueThisWeek();
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
