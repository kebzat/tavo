<?php

namespace App\Filament\Tools\Pages;

use App\Filament\Tools\Pages\Ads\AdsClient;
use App\Filament\Tools\Resources\Clients\ClientResource;
use App\Filament\Tools\Resources\Deals\DealResource;
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
 * Co za měsíc fakturovat: paušály klientů s hodinami nad rámec
 * a vyhrané jednorázové zakázky z CRM. U každého řádku je vidět,
 * jestli už faktura odešla. Pod tím kapacita, kolik kdo odpracoval
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

    public function mount(): void
    {
        $this->month ??= now()->format('Y-m');
    }

    /**
     * Kolik věcí z minulého měsíce a z vyhraných zakázek ještě čeká na fakturu.
     * Paušál se fakturuje po skončení měsíce, proto minulý.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = self::pending(now()->subMonthNoOverflow()->startOfMonth());

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Nevyfakturováno za '.now()->subMonthNoOverflow()->translatedFormat('F').' a vyhrané zakázky bez faktury';
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

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $rows = $this->rows();
        $deals = $this->deals();

        $total = $rows->sum('total_raw') + $deals->sum('value_raw');
        $invoiced = $rows->sum('invoiced_raw') + $deals->where('invoiced', true)->sum('value_raw');

        return [
            'rows' => $rows,
            'deals' => $deals,
            'capacity' => $this->capacity(),
            'total' => Format::money($total),
            'invoiced' => Format::money($invoiced),
            'remaining' => Format::money(max(0, $total - $invoiced)),
            'remaining_raw' => max(0, $total - $invoiced),
        ];
    }

    /**
     * Paušály a hodiny po klientech.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(): Collection
    {
        return self::billings($this->monthDate())->map(fn (Billing $billing): array => [
            'id' => $billing->client->getKey(),
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
     * Vyhrané jednorázové zakázky: všechny ještě nevyfakturované, bez ohledu
     * na měsíc (ať žádná nezapadne), a ty vyfakturované v zobrazeném měsíci.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function deals(): Collection
    {
        $month = $this->monthDate();

        return Deal::query()
            ->billable()
            ->with('company')
            ->where(fn ($query) => $query
                ->whereNull('invoiced_at')
                ->orWhereBetween('invoiced_at', [$month, $month->copy()->endOfMonth()]))
            ->orderBy('won_at')
            ->get()
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
            ]);
    }

    /**
     * Hodiny po lidech za měsíc.
     *
     * @return list<array{name: string, billable: string, other: string}>
     */
    public function capacity(): array
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

    public function markInvoiced(int $clientId): void
    {
        $client = Client::findOrFail($clientId);
        Billing::for($client, $this->monthDate())->markInvoiced(Auth::id());

        Notification::make()->success()->title($client->name.': vyfakturováno')->send();
    }

    public function unmarkInvoiced(int $clientId): void
    {
        $client = Client::findOrFail($clientId);
        Billing::for($client, $this->monthDate())->unmarkInvoiced();

        Notification::make()->title($client->name.': zase čeká na fakturu')->send();
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

    /**
     * Klienti s paušálem nebo s odpracovaným časem v měsíci. Bez nulových
     * řádků, kromě těch, které už někdo označil.
     *
     * @return Collection<int, Billing>
     */
    private static function billings(Carbon $month): Collection
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
            ->get()
            ->map(fn (Client $client): Billing => Billing::for($client, $month))
            ->filter(fn (Billing $billing): bool => $billing->total() > 0 || $billing->billableHours > 0 || $billing->isInvoiced() || $billing->tentativeFee > 0)
            ->values();
    }

    /** Kolik řádků čeká na fakturu: klienti za měsíc a všechny nevyfakturované zakázky. */
    private static function pending(Carbon $month): int
    {
        $clients = self::billings($month)
            ->filter(fn (Billing $billing): bool => $billing->tentativeFee > 0
                || ($billing->total() > 0 && (! $billing->isInvoiced() || $billing->changedSinceInvoice())))
            ->count();

        return $clients + Deal::query()->billable()->whereNull('invoiced_at')->count();
    }
}
