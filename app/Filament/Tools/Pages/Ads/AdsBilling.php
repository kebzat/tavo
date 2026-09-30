<?php

namespace App\Filament\Tools\Pages\Ads;

use App\Models\Client;
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
use Livewire\Attributes\Url;

/**
 * Co za měsíc fakturovat: paušál a hodiny nad paušál po klientech.
 * Pod tím kapacita, kolik kdo odpracoval (BRAND-STRATEGY §16.1).
 */
class AdsBilling extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Fakturace';

    protected static ?string $title = 'Fakturace';

    protected static string|\UnitEnum|null $navigationGroup = 'Reklamy';

    protected static ?int $navigationSort = 50;

    protected static ?string $slug = 'reklamy/fakturace';

    protected string $view = 'filament.tools.pages.ads.billing';

    /** Měsíc ve tvaru Y-m. V adrese, ať se dá poslat odkaz. */
    #[Url]
    public ?string $month = null;

    public function mount(): void
    {
        $this->month ??= now()->format('Y-m');
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

    /**
     * Klienti s paušálem nebo s odpracovaným časem v měsíci.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(): Collection
    {
        $month = $this->monthDate();
        $withTime = TimeEntry::query()->inMonth($month->year, $month->month)->distinct()->pluck('client_id');

        return Client::query()
            ->with('adSettings')
            ->where(fn ($query) => $query
                ->whereIn('id', $withTime)
                ->orWhereHas('adSettings', fn ($query) => $query->where('fee_czk', '>', 0)))
            ->orderBy('name')
            ->get()
            ->map(function (Client $client) use ($month): array {
                $billing = Billing::for($client, $month);

                return [
                    'id' => $client->getKey(),
                    'name' => $client->name,
                    'url' => AdsClient::getUrl(['client' => $client->getKey()]),
                    'uninvoiced' => $billing->uninvoicedEntries,
                    'missing_rate' => $billing->missingRate(),
                    'total_raw' => $billing->total(),
                ] + $billing->formatted();
            });
    }

    public function total(): string
    {
        return Format::money($this->rows()->sum('total_raw'));
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
        $count = Billing::for(Client::findOrFail($clientId), $this->monthDate())->markInvoiced();

        Notification::make()->success()->title("Označeno {$count} záznamů jako vyfakturované")->send();
    }
}
