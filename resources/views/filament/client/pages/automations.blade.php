@php
    use App\Models\AutomationEvent;
@endphp

<x-filament-panels::page>
    @if ($this->isAdmin())
        <div class="flex flex-wrap items-center gap-3">
            <span class="text-sm text-gray-500 dark:text-gray-400">Náhled za klienta:</span>
            <div class="w-full sm:w-72">
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="clientId">
                        <option value="">Všichni klienti</option>
                        @foreach ($this->clientOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>
    @endif

    @forelse ($this->automations as $automation)
        @php
            $parcels = $automation->parcels();
            $returns = $automation->returns();
            $events = $this->events($automation);
        @endphp

        <x-filament::section :icon="$automation->enabled ? 'heroicon-o-bolt' : 'heroicon-o-pause-circle'" :icon-color="$automation->enabled ? 'success' : 'gray'">
            <x-slot name="heading">
                {{ $automation->type->getLabel() }}@if ($this->isAdmin() && ! $this->clientId) · {{ $automation->client->name }}@endif
            </x-slot>
            <x-slot name="description">{{ $automation->type->description() }}</x-slot>

            <div class="mb-6 flex flex-wrap items-center gap-3">
                <x-filament::badge :color="$automation->enabled ? 'success' : 'gray'">
                    {{ $automation->enabled ? 'Zapnuto' : 'Vypnuto' }}
                </x-filament::badge>
                {{ ($this->settingsAction)(['automation' => $automation->id]) }}
            </div>
            <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Připomínka e-mailem</dt>
                    <dd class="font-semibold text-gray-950 dark:text-white">
                        {{ $automation->setting('send_reminder') ? 'Ano, '.$automation->setting('remind_days_before').' '.((int) $automation->setting('remind_days_before') === 1 ? 'den' : (((int) $automation->setting('remind_days_before')) < 5 ? 'dny' : 'dní')).' před koncem' : 'Ne' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">SMS poslední den</dt>
                    <dd class="font-semibold text-gray-950 dark:text-white">{{ $automation->setting('send_last_day') ? 'Ano' : 'Ne' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">E-mail k vrácené zásilce</dt>
                    <dd class="font-semibold text-gray-950 dark:text-white">{{ $automation->setting('send_returned') ? 'Ano' : 'Ne' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Kdy se posílá</dt>
                    <dd class="font-semibold text-gray-950 dark:text-white">{{ $automation->setting('send_from') }}:00–{{ $automation->setting('send_until') }}:00</dd>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">V noci změny počkají do rána.</p>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Naposledy běžela</dt>
                    <dd class="font-semibold text-gray-950 dark:text-white">
                        @if ($automation->last_run_at)
                            {{ $automation->last_run_at->timezone('Europe/Prague')->format('j. n. G:i') }}
                        @else
                            Zatím ne
                        @endif
                    </dd>
                    @if ($automation->last_run_at)
                        @if ($automation->last_error)
                            <p class="mt-1 text-sm text-danger-600 dark:text-danger-400">S chybou: {{ $automation->last_error }}</p>
                        @else
                            <p class="mt-1 text-sm text-success-600 dark:text-success-400">Bez chyby</p>
                        @endif
                    @endif
                    @if ($automation->last_warning)
                        <p class="mt-1 text-sm text-warning-600 dark:text-warning-400">{{ $automation->last_warning }}</p>
                    @endif
                </div>
            </dl>
        </x-filament::section>

        @php
            $boxes = $automation->giftBoxes();
            $boxesToPack = array_values(array_filter($boxes, fn (array $row): bool => $row['to_pack']));
            $boxesDone = array_values(array_filter($boxes, fn (array $row): bool => ! $row['to_pack']));
            $boxesAt = \App\Support\Automations\GiftBoxes::updatedAt($automation);
        @endphp

        @if ($boxes !== [])
            <x-filament::section icon="heroicon-o-gift" icon-color="primary">
                <x-slot name="heading">Dárkové balíčky k zabalení ({{ count($boxesToPack) }})</x-slot>
                <x-slot name="description">
                    Nejstarší objednávky nahoře. Obrázek ukazuje, jak krabici poskládat, kliknutím se otevře větší.
                    Obnovuje se samo každých 10 minut{{ $boxesAt ? ', naposledy v '.$boxesAt->timezone('Europe/Prague')->format('G:i') : '' }}.
                </x-slot>
                <x-slot name="afterHeader">{{ ($this->refreshBoxesAction)(['automation' => $automation->id]) }}</x-slot>

                @if ($boxesToPack === [])
                    <p class="text-sm text-gray-500 dark:text-gray-400">Teď není co balit.</p>
                @else
                    @include('filament.client.pages.partials.gift-boxes', ['rows' => $boxesToPack])
                @endif
            </x-filament::section>

            @if ($boxesDone !== [])
                <x-filament::section collapsible collapsed>
                    <x-slot name="heading">Odeslané balíčky za posledních {{ \App\Support\Automations\GiftBoxes::DONE_DAYS }} dní ({{ count($boxesDone) }})</x-slot>

                    @include('filament.client.pages.partials.gift-boxes', ['rows' => $boxesDone])
                </x-filament::section>
            @endif
        @endif

        <x-filament::section>
            <x-slot name="heading">Zásilky na výdejních místech ({{ count($parcels) }})</x-slot>
            <x-slot name="description">Stav z posledního běhu. Nejdřív ty, kterým úložní doba končí nejdřív.</x-slot>

            @if ($parcels === [])
                <p class="text-sm text-gray-500 dark:text-gray-400">Teď na výdejním místě nic neleží.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[36rem] text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-white/10">
                                <th class="whitespace-nowrap px-3 py-2 font-semibold text-gray-950 dark:text-white">Objednávka</th>
                                <th class="whitespace-nowrap px-3 py-2 font-semibold text-gray-950 dark:text-white">Dopravce</th>
                                <th class="whitespace-nowrap px-3 py-2 font-semibold text-gray-950 dark:text-white">Do kdy</th>
                                <th class="whitespace-nowrap px-3 py-2 font-semibold text-gray-950 dark:text-white">Zbývá</th>
                                <th class="px-3 py-2 font-semibold text-gray-950 dark:text-white">Co se poslalo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($parcels as $parcel)
                                <tr class="border-b border-gray-100 last:border-0 dark:border-white/5">
                                    <td class="whitespace-nowrap px-3 py-2 font-semibold tabular-nums text-gray-950 dark:text-white">@include('filament.client.pages.partials.order-number', ['number' => $parcel['order']])</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-gray-600 dark:text-gray-400">{{ match ($parcel['carrier']) { 'zasilkovna', 'packeta' => 'Zásilkovna', 'ppl' => 'PPL', 'dpd' => 'DPD', 'balikovna' => 'Balíkovna', null => '–', default => ucfirst($parcel['carrier']) } }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 tabular-nums text-gray-600 dark:text-gray-400">{{ $parcel['stored_until'] ? \Illuminate\Support\Carbon::parse($parcel['stored_until'])->format('j. n. Y') : 'nevíme' }}</td>
                                    <td @class([
                                        'whitespace-nowrap px-3 py-2 tabular-nums',
                                        'font-semibold text-danger-600 dark:text-danger-400' => $parcel['days_left'] !== null && $parcel['days_left'] <= 0,
                                        'text-warning-600 dark:text-warning-400' => $parcel['days_left'] !== null && $parcel['days_left'] > 0 && $parcel['days_left'] <= 2,
                                        'text-gray-600 dark:text-gray-400' => $parcel['days_left'] === null || $parcel['days_left'] > 2,
                                    ])>
                                        @if ($parcel['days_left'] === null)
                                            –
                                        @elseif ($parcel['days_left'] <= 0)
                                            poslední den
                                        @else
                                            {{ $parcel['days_left'] }} {{ $parcel['days_left'] === 1 ? 'den' : ($parcel['days_left'] < 5 ? 'dny' : 'dní') }}
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">
                                        {{ collect($parcel['sent'])->map(fn ($kind) => AutomationEvent::kindLabel($kind))->implode(', ') ?: 'zatím nic' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Vrácené zásilky za posledních {{ \App\Support\Automations\ParcelPickup::WATCH_RETURNS_DAYS }} dní ({{ count($returns) }})</x-slot>
            <x-slot name="description">Zásilky, které se vrací nebo už vrátily k vám. Nejnovější nahoře.</x-slot>

            @if ($returns === [])
                <p class="text-sm text-gray-500 dark:text-gray-400">Nic se nevracelo.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[36rem] text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-white/10">
                                <th class="whitespace-nowrap px-3 py-2 font-semibold text-gray-950 dark:text-white">Objednávka</th>
                                <th class="whitespace-nowrap px-3 py-2 font-semibold text-gray-950 dark:text-white">Dopravce</th>
                                <th class="whitespace-nowrap px-3 py-2 font-semibold text-gray-950 dark:text-white">Zásilka</th>
                                <th class="whitespace-nowrap px-3 py-2 font-semibold text-gray-950 dark:text-white">Stav objednávky</th>
                                <th class="whitespace-nowrap px-3 py-2 font-semibold text-gray-950 dark:text-white">Vrací se od</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($returns as $return)
                                @php
                                    $back = ! empty($return['returned']) || ($return['status'] ?? null) === $automation->status('returned');
                                @endphp
                                <tr class="border-b border-gray-100 last:border-0 dark:border-white/5">
                                    <td class="whitespace-nowrap px-3 py-2 font-semibold tabular-nums text-gray-950 dark:text-white">@include('filament.client.pages.partials.order-number', ['number' => $return['order']])</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-gray-600 dark:text-gray-400">{{ match ($return['carrier'] ?? null) { 'zasilkovna', 'packeta' => 'Zásilkovna', 'ppl' => 'PPL', 'dpd' => 'DPD', 'balikovna' => 'Balíkovna', null => '–', default => ucfirst($return['carrier']) } }}</td>
                                    <td class="whitespace-nowrap px-3 py-2">
                                        <x-filament::badge :color="$back ? 'danger' : 'warning'">{{ $back ? 'Vrátila se' : 'Na cestě zpět' }}</x-filament::badge>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2 text-gray-600 dark:text-gray-400">{{ ($return['status'] ?? null) ?: '–' }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 tabular-nums text-gray-600 dark:text-gray-400">{{ \Illuminate\Support\Carbon::parse($return['since'])->format('j. n.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Co se poslalo</x-slot>
            <x-slot name="description">Posledních 50 zpráv. „Čeká na Upgates“ = e-shop si změnu stáhne do hodiny, pak zprávu pošle a tady se ukáže „Potvrzeno“.</x-slot>

            @if ($events->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">Zatím se nic neposlalo.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[36rem] text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-white/10">
                                <th class="whitespace-nowrap px-3 py-2 font-semibold text-gray-950 dark:text-white">Datum</th>
                                <th class="whitespace-nowrap px-3 py-2 font-semibold text-gray-950 dark:text-white">Objednávka</th>
                                <th class="px-3 py-2 font-semibold text-gray-950 dark:text-white">Zpráva</th>
                                <th class="px-3 py-2 font-semibold text-gray-950 dark:text-white">Stav</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($events as $event)
                                <tr class="border-b border-gray-100 last:border-0 dark:border-white/5">
                                    <td class="whitespace-nowrap px-3 py-2 tabular-nums text-gray-600 dark:text-gray-400">{{ $event->created_at->timezone('Europe/Prague')->format('j. n. G:i') }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 font-semibold tabular-nums text-gray-950 dark:text-white">@include('filament.client.pages.partials.order-number', ['number' => $event->order_number])</td>
                                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">
                                        {{ AutomationEvent::kindLabel($event->kind) }}
                                        @if ($event->detail)
                                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $event->detail }}</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        <x-filament::badge :color="AutomationEvent::stateColor($event->state)">{{ AutomationEvent::stateLabel($event->state) }}</x-filament::badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    @empty
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">Zatím tu žádná automatizace není. Nastavuje ji TAVEO, ozvěte se nám.</p>
        </x-filament::section>
    @endforelse

</x-filament-panels::page>
