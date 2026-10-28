<?php

namespace App\Filament\Client\Pages;

use App\Models\Automation;
use App\Models\AutomationEvent;
use App\Models\Client;
use App\Models\User;
use App\Support\Automations\AutomationException;
use App\Support\Automations\GiftBoxes;
use App\Support\Automations\UpgatesOrderLinks;
use App\Support\Automations\UpgatesOrders;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

/**
 * Automatizace e-shopu v účtu klienta: zapnuto, co se posílá, kdy naposledy
 * běžela, zásilky na výdejních místech a co se poslalo. Bez jmen, adres,
 * e-mailů a telefonů zákazníků (nemáme je, jen čísla objednávek). Klient
 * mění jen přepínače a počet dní, technické nastavení je v nástrojích.
 * Správce vidí všechny klienty (výběr nahoře). Viz docs/AUTOMATIZACE.md.
 */
class Automations extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?string $navigationLabel = 'Automatizace';

    protected static ?string $title = 'Automatizace';

    protected static ?string $slug = 'automatizace';

    protected string $view = 'filament.client.pages.automations';

    /** Jen pro správce: kterého klienta ukázat. Klientovi se nepoužije. */
    #[Url(as: 'klient')]
    public ?int $clientId = null;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && ($user->isAdmin() || ($user->isClient() && $user->client_id !== null));
    }

    public function getSubheading(): ?string
    {
        return 'Co za vás e-shop dělá sám. Jména, adresy ani kontakty zákazníků tu nejsou, jen čísla objednávek.';
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }

    public function isAdmin(): bool
    {
        return $this->user()->isAdmin();
    }

    /** @return array<int, string> klienti s automatizací (výběr pro správce) */
    #[Computed]
    public function clientOptions(): array
    {
        return $this->isAdmin()
            ? Client::query()->whereHas('automations')->orderBy('name')->pluck('name', 'id')->all()
            : [];
    }

    /** @return Collection<int, Automation> */
    #[Computed]
    public function automations(): Collection
    {
        return Automation::query()
            ->visibleTo($this->user())
            ->when($this->isAdmin() && $this->clientId, fn ($query) => $query->where('client_id', $this->clientId))
            ->with('client')
            ->orderBy('client_id')
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int, AutomationEvent> posledních 50 zpráv */
    public function events(Automation $automation): Collection
    {
        return $automation->events()->latest('id')->limit(50)->get();
    }

    private function find(mixed $id): Automation
    {
        return Automation::query()->visibleTo($this->user())->findOrFail($id);
    }

    public function updatedClientId(): void
    {
        unset($this->automations);
    }

    /** Obnoví hned přehled dárkových balíčků (jinak každých 10 minut). Zákazníkům nic neposílá. */
    public function refreshBoxesAction(): Action
    {
        return Action::make('refreshBoxes')
            ->label('Aktualizovat')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->size('sm')
            ->action(function (array $arguments): void {
                $automation = $this->find($arguments['automation'] ?? 0);
                $key = 'gift-boxes-refresh:'.$automation->getKey();

                if (RateLimiter::tooManyAttempts($key, 1)) {
                    Notification::make()->info()->title('Právě se aktualizovalo')->body('Zkus to znovu za '.RateLimiter::availableIn($key).' s.')->send();

                    return;
                }

                RateLimiter::hit($key, GiftBoxes::REFRESH_COOLDOWN_SECONDS);

                try {
                    $count = GiftBoxes::refresh($automation, app(UpgatesOrders::class), app(UpgatesOrderLinks::class));
                } catch (AutomationException $e) {
                    Notification::make()->danger()->title('Nepodařilo se načíst objednávky')->body($e->getMessage())->send();

                    return;
                } catch (\Throwable $e) {
                    report($e);
                    Notification::make()->danger()->title('Nepodařilo se načíst objednávky')->body('Zkus to za chvíli znovu.')->send();

                    return;
                }

                unset($this->automations);
                Notification::make()->success()->title('Aktualizováno')->body('K zabalení: '.$count.'.')->send();
            });
    }

    /** @return array<int, string> hodiny pro výběr (8 => „8:00“) */
    public static function hours(int $from, int $to): array
    {
        return collect(range($from, $to))->mapWithKeys(fn (int $hour): array => [$hour => $hour.':00'])->all();
    }

    /** Přepínače, počet dní a hodiny posílání. Nic technického, to je v nástrojích. */
    public function settingsAction(): Action
    {
        return Action::make('settings')
            ->label('Upravit')
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->color('gray')
            ->size('sm')
            ->modalHeading(fn (array $arguments): string => $this->find($arguments['automation'] ?? 0)->type->getLabel())
            ->modalWidth('lg')
            ->fillForm(function (array $arguments): array {
                $automation = $this->find($arguments['automation'] ?? 0);

                return [
                    'enabled' => $automation->enabled,
                    ...collect(Automation::CLIENT_SETTINGS)->mapWithKeys(fn (string $key): array => [$key => $automation->setting($key)])->all(),
                ];
            })
            ->schema([
                Toggle::make('enabled')->label('Zapnuto')->helperText('Vypnutá automatizace nic neposílá.'),
                Toggle::make('send_reminder')->label('Připomínka vyzvednutí e-mailem')
                    ->helperText('Jen u zásilek, které na výdejní místo dorazí po zapnutí automatizace.'),
                TextInput::make('remind_days_before')
                    ->label('Kolik dní před koncem úložní doby připomenout')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(6)
                    ->required()
                    ->suffix('dní'),
                Toggle::make('send_last_day')->label('SMS v poslední den k vyzvednutí'),
                Toggle::make('send_returned')->label('E-mail, když se zásilka vrátí'),
                Grid::make(2)->schema([
                    Select::make('send_from')->label('Posílat od')->options(self::hours(0, 23))->required()->selectablePlaceholder(false),
                    Select::make('send_until')->label('do')->options(self::hours(1, 24))->required()->selectablePlaceholder(false)
                        ->gt('send_from')
                        ->validationMessages(['gt' => 'Konec musí být později než začátek.']),
                ]),
            ])
            ->modalSubmitActionLabel('Uložit')
            ->action(function (array $data, array $arguments): void {
                $automation = $this->find($arguments['automation'] ?? 0);
                $settings = $automation->settings ?? [];

                foreach (Automation::CLIENT_SETTINGS as $key) {
                    $settings[$key] = match ($key) {
                        'remind_days_before' => max(1, min(6, (int) $data[$key])),
                        'send_from' => max(0, min(23, (int) $data[$key])),
                        'send_until' => max((int) $settings['send_from'] + 1, min(24, (int) $data[$key])),
                        default => (bool) $data[$key],
                    };
                }

                $automation->update(['enabled' => (bool) $data['enabled'], 'settings' => $settings]);
                unset($this->automations);

                Notification::make()->success()->title('Uloženo')->send();
            });
    }
}
