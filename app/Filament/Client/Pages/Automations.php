<?php

namespace App\Filament\Client\Pages;

use App\Models\Automation;
use App\Models\AutomationEvent;
use App\Models\Client;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
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

    /** Přepínače a počet dní. Nic technického, to je v nástrojích. */
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
            ])
            ->modalSubmitActionLabel('Uložit')
            ->action(function (array $data, array $arguments): void {
                $automation = $this->find($arguments['automation'] ?? 0);
                $settings = $automation->settings ?? [];

                foreach (Automation::CLIENT_SETTINGS as $key) {
                    $settings[$key] = $key === 'remind_days_before' ? max(1, min(6, (int) $data[$key])) : (bool) $data[$key];
                }

                $automation->update(['enabled' => (bool) $data['enabled'], 'settings' => $settings]);
                unset($this->automations);

                Notification::make()->success()->title('Uloženo')->send();
            });
    }
}
