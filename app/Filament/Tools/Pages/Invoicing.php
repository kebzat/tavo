<?php

namespace App\Filament\Tools\Pages;

use App\Enums\WorkArea;
use App\Filament\Tools\Pages\Ads\AdsClient;
use App\Filament\Tools\Resources\Clients\ClientResource;
use App\Filament\Tools\Resources\Deals\DealResource;
use App\Filament\Tools\Resources\TimeEntries\TimeEntryResource;
use App\Models\Client;
use App\Models\Crm\Deal;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\Ads\Billing;
use App\Support\Ads\Format;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

/**
 * Co za měsíc fakturovat, rozdělené po oblastech: vývoj fakturuje Tom,
 * marketing Pavel (users.billing_area). V každé oblasti paušály klientů
 * s hodinami nad rámec a vyhrané jednorázové zakázky z CRM, u každého
 * řádku stav faktury. Pod tím kapacita, kolik kdo odpracoval
 * (BRAND-STRATEGY §16.1).
 */
class Invoicing extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Fakturace';

    protected static ?string $title = 'Fakturace';

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 45;

    protected static ?string $slug = 'fakturace';

    protected string $view = 'filament.tools.pages.invoicing';

    /** Měsíc ve tvaru Y-m. V adrese, ať se dá poslat odkaz. */
    #[Url]
    public ?string $month = null;

    /** web, marketing nebo vse. Výchozí je oblast, kterou přihlášený fakturuje. */
    #[Url]
    public ?string $oblast = null;

    public function mount(): void
    {
        $this->month ??= now()->format('Y-m');
        $this->oblast ??= Auth::user()?->billing_area?->value ?? 'vse';
    }

    /**
     * Kolik věcí z minulého měsíce a z vyhraných zakázek ještě čeká na fakturu,
     * v oblasti přihlášeného. Paušál se fakturuje po skončení měsíce, proto minulý.
     */
    public static function getNavigationBadge(): ?string
    {
        $area = Auth::user()?->billing_area;
        $count = collect($area ? [$area] : WorkArea::cases())
            ->sum(fn (WorkArea $area): int => self::pending(now()->subMonthNoOverflow()->startOfMonth(), $area));

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        $area = Auth::user()?->billing_area;

        return 'Nevyfakturováno za '.now()->subMonthNoOverflow()->translatedFormat('F').' a vyhrané zakázky bez faktury'
            .($area ? ' ('.$area->getLabel().')' : '');
    }

    public function getSubheading(): ?string
    {
        return $this->monthDate()->translatedFormat('F Y');
    }

    public function monthDate(): Carbon
    {
        return Carbon::parse($this->month.'-01')->startOfMonth();
    }

    public function previousMonth(): void
    {
        $this->month = $this->monthDate()->subMonthNoOverflow()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = $this->monthDate()->addMonthNoOverflow()->format('Y-m');
    }

    public function showArea(string $area): void
    {
        $this->oblast = WorkArea::tryFrom($area)?->value ?? 'vse';
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $sections = collect($this->shownAreas())->map(fn (WorkArea $area): array => $this->section($area));
        $total = $sections->sum('total_raw');
        $invoiced = $sections->sum('invoiced_raw');

        return [
            'filters' => $this->filters(),
            'sections' => $sections,
            'unassigned' => $this->unassigned(),
            'capacity' => $this->capacity(),
            'total' => Format::money($total),
            'invoiced' => Format::money($invoiced),
            'remaining' => Format::money(max(0, $total - $invoiced)),
            'remaining_raw' => max(0, $total - $invoiced),
        ];
    }

    /** @return list<WorkArea> */
    private function shownAreas(): array
    {
        $area = WorkArea::tryFrom((string) $this->oblast);

        return $area ? [$area] : WorkArea::cases();
    }

    /**
     * Přepínač nahoře: Vše, Vývoj webu · Tom, Marketing · Pavel.
     *
     * @return list<array{key: string, label: string, active: bool}>
     */
    private function filters(): array
    {
        $filters = [['key' => 'vse', 'label' => 'Vše', 'active' => WorkArea::tryFrom((string) $this->oblast) === null]];

        foreach (WorkArea::cases() as $area) {
            $invoicer = self::invoicer($area);
            $filters[] = [
                'key' => $area->value,
                'label' => $area->getLabel().($invoicer ? ' · '.$invoicer : ''),
                'active' => $this->oblast === $area->value,
            ];
        }

        return $filters;
    }

    /** @return array<string, mixed> */
    private function section(WorkArea $area): array
    {
        $rows = $this->rows($area);
        $deals = $this->deals($area);
        $total = $rows->sum('total_raw') + $deals->sum('value_raw');
        $invoiced = $rows->sum('invoiced_raw') + $deals->where('invoiced', true)->sum('value_raw');
        $invoicer = self::invoicer($area);

        return [
            'key' => $area->value,
            'heading' => $area->getLabel().($invoicer ? ' · fakturuje '.$invoicer : ''),
            'summary' => 'K fakturaci '.Format::money($total).' · vyfakturováno '.Format::money($invoiced).' · zbývá '.Format::money(max(0, $total - $invoiced)),
            'rows' => $rows,
            'deals' => $deals,
            'total_raw' => $total,
            'invoiced_raw' => $invoiced,
        ];
    }

    /**
     * Paušály a hodiny po klientech v jedné oblasti.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function rows(WorkArea $area): Collection
    {
        return self::billings($this->monthDate(), $area)->map(fn (Billing $billing): array => [
            'id' => $billing->client->getKey(),
            'area' => $area->value,
            'name' => $billing->client->name,
            'url' => ClientResource::getUrl('edit', ['record' => $billing->client]),
            'ads_url' => $billing->client->adSettings ? AdsClient::getUrl(['client' => $billing->client->getKey()]) : null,
            'missing_rate' => $billing->missingRate(),
            'total_raw' => $billing->total(),
            'invoiced' => $billing->isInvoiced(),
            'invoiced_raw' => $billing->invoice?->amount_czk ?? 0,
            'invoiced_note' => $billing->invoice
                ? 'Vyfakturováno '.$billing->invoice->invoiced_at->format('j. n.').' na '.Format::money($billing->invoice->amount_czk)
                    .($billing->invoice->user ? ' ('.$billing->invoice->user->name.')' : '')
                : null,
            'changed' => $billing->changedSinceInvoice(),
            'tentative' => $billing->tentativeFee > 0 ? Format::money($billing->tentativeFee) : null,
        ] + $billing->formatted());
    }

    /**
     * Vyhrané jednorázové zakázky oblasti: všechny ještě nevyfakturované, bez
     * ohledu na měsíc (ať žádná nezapadne), a ty vyfakturované v zobrazeném měsíci.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function deals(?WorkArea $area): Collection
    {
        return $this->billableDeals()
            ->filter(fn (Deal $deal): bool => $deal->billingArea() === $area)
            ->map(fn (Deal $deal): array => [
                'id' => $deal->getKey(),
                'company' => $deal->company?->name,
                'title' => $deal->title,
                'package' => $deal->package?->getLabel(),
                'url' => DealResource::getUrl('edit', ['record' => $deal]),
                'won' => $deal->won_at?->format('j. n. Y'),
                'value' => Format::money($deal->value_czk),
                'value_raw' => (int) $deal->value_czk,
                'invoiced' => $deal->invoiced_at !== null,
                'invoiced_note' => $deal->invoiced_at ? 'Vyfakturováno '.$deal->invoiced_at->format('j. n.') : null,
            ])
            ->values();
    }

    /** @return Collection<int, Deal> */
    private function billableDeals(): Collection
    {
        $month = $this->monthDate();

        return once(fn (): Collection => Deal::query()
            ->billable()
            ->with('company')
            ->where(fn ($query) => $query
                ->whereNull('invoiced_at')
                ->orWhereBetween('invoiced_at', [$month, $month->copy()->endOfMonth()]))
            ->orderBy('won_at')
            ->get());
    }

    /**
     * Co nejde přiřadit oblasti: hodiny bez oblasti u klienta s webem
     * i marketingem a zakázky s balíčkem Jiné. Dokud se nedoplní, nejsou
     * v žádné faktuře.
     *
     * @return array{hours: list<array{name: string, hours: string, url: string}>, deals: Collection<int, array<string, mixed>>}
     */
    private function unassigned(): array
    {
        $month = $this->monthDate();

        $hours = self::clientsFor($month)
            ->map(fn (Client $client): Billing => Billing::for($client, $month, WorkArea::Web))
            ->filter(fn (Billing $billing): bool => $billing->unassignedHours > 0)
            ->map(fn (Billing $billing): array => [
                'name' => $billing->client->name,
                'hours' => Format::number($billing->unassignedHours, 1).' h',
                'url' => TimeEntryResource::getUrl().'?'.http_build_query(['filters' => [
                    'client' => ['value' => $billing->client->getKey()],
                    'month' => ['value' => $month->format('Y-m')],
                ]]),
            ])
            ->values()
            ->all();

        return ['hours' => $hours, 'deals' => $this->deals(null)];
    }

    /**
     * Hodiny po lidech za měsíc.
     *
     * @return list<array{name: string, billable: string, other: string}>
     */
    private function capacity(): array
    {
        $month = $this->monthDate();
        $entries = TimeEntry::query()->inMonth($month->year, $month->month)->get()->groupBy('user_id');

        return User::query()->orderBy('name')->get()
            ->filter(fn (User $user): bool => $entries->has($user->getKey()))
            ->map(fn (User $user): array => [
                'name' => $user->name,
                'billable' => Format::number($entries[$user->getKey()]->where('billable', true)->sum('minutes') / 60, 1).' h',
                'other' => Format::number($entries[$user->getKey()]->where('billable', false)->sum('minutes') / 60, 1).' h',
            ])
            ->values()
            ->all();
    }

    public function markInvoiced(int $clientId, string $area): void
    {
        $client = Client::findOrFail($clientId);
        $area = WorkArea::from($area);
        Billing::for($client, $this->monthDate(), $area)->markInvoiced(Auth::id());

        Notification::make()->success()->title($client->name.' · '.$area->getLabel().': vyfakturováno')->send();
    }

    public function unmarkInvoiced(int $clientId, string $area): void
    {
        $client = Client::findOrFail($clientId);
        $area = WorkArea::from($area);
        Billing::for($client, $this->monthDate(), $area)->unmarkInvoiced();

        Notification::make()->title($client->name.' · '.$area->getLabel().': zase čeká na fakturu')->send();
    }

    public function markDealInvoiced(int $dealId): void
    {
        $deal = Deal::query()->billable()->findOrFail($dealId);
        $deal->update(['invoiced_at' => now()]);

        Notification::make()->success()->title($deal->title.': vyfakturováno')->send();
    }

    public function unmarkDealInvoiced(int $dealId): void
    {
        $deal = Deal::query()->billable()->findOrFail($dealId);
        $deal->update(['invoiced_at' => null]);

        Notification::make()->title($deal->title.': zase čeká na fakturu')->send();
    }

    /** Jméno toho, kdo oblast fakturuje. Null, když ji nikdo nemá (fakturuje se společně). */
    private static function invoicer(WorkArea $area): ?string
    {
        return User::query()->where('billing_area', $area->value)->orderBy('id')->pluck('name')->implode(', ') ?: null;
    }

    /**
     * Klienti s paušálem, s odpracovaným časem v měsíci nebo s fakturou za něj.
     *
     * @return Collection<int, Client>
     */
    private static function clientsFor(Carbon $month): Collection
    {
        $withTime = TimeEntry::query()->inMonth($month->year, $month->month)->distinct()->pluck('client_id');

        return Client::query()
            ->with(['adSettings', 'retainers'])
            ->where(fn ($query) => $query
                ->whereIn('id', $withTime)
                ->orWhereHas('adSettings', fn ($query) => $query->where('fee_czk', '>', 0))
                ->orWhereHas('retainers')
                ->orWhereHas('invoices', fn ($query) => $query->whereDate('month', $month->toDateString())))
            ->orderBy('name')
            ->get();
    }

    /**
     * Klienti oblasti v měsíci. Bez nulových řádků, kromě už označených.
     *
     * @return Collection<int, Billing>
     */
    private static function billings(Carbon $month, WorkArea $area): Collection
    {
        return self::clientsFor($month)
            ->map(fn (Client $client): Billing => Billing::for($client, $month, $area))
            ->filter(fn (Billing $billing): bool => $billing->total() > 0 || $billing->billableHours > 0 || $billing->isInvoiced() || $billing->tentativeFee > 0)
            ->values();
    }

    /** Kolik řádků oblasti čeká na fakturu: klienti za měsíc a všechny nevyfakturované zakázky. */
    private static function pending(Carbon $month, WorkArea $area): int
    {
        $clients = self::billings($month, $area)
            ->filter(fn (Billing $billing): bool => $billing->tentativeFee > 0
                || ($billing->total() > 0 && (! $billing->isInvoiced() || $billing->changedSinceInvoice())))
            ->count();

        $deals = Deal::query()->billable()->whereNull('invoiced_at')->get()
            ->filter(fn (Deal $deal): bool => $deal->billingArea() === $area)
            ->count();

        return $clients + $deals;
    }
}
