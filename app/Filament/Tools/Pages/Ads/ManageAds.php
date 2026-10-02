<?php

namespace App\Filament\Tools\Pages\Ads;

use App\Enums\Ads\AdPlatform;
use App\Models\Ads\AdSyncRun;
use App\Settings\AdsSettings;
use App\Support\Ads\AccountDirectory;
use App\Support\Ads\Platforms\AdsApiException;
use App\Support\Ads\Platforms\ApiGuard;
use App\Support\Ads\Platforms\Ga4;
use App\Support\Ads\Platforms\Platforms;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;

/**
 * Nastavení reklam: stav napojení na Metu, příjemci ranního souhrnu,
 * prahy upozornění a poslední synchronizace.
 */
class ManageAds extends SettingsPage
{
    protected static string $settings = AdsSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Nastavení reklam';

    protected static ?string $title = 'Nastavení reklam';

    protected static string|\UnitEnum|null $navigationGroup = 'Reklamy';

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'reklamy/nastaveni';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Napojení')
                ->description('Přístupy jsou v .env na serveru, sem se nezadávají. Postup je v docs/ADS.md. Spojení ověříte tlačítkem nahoře.')
                ->columns(2)
                ->schema(collect(AdPlatform::cases())->map(fn (AdPlatform $platform): TextEntry => TextEntry::make('platform_'.$platform->value)
                    ->label($platform->getLabel())
                    ->state(fn (): string => $this->platformState($platform))
                    ->color(fn (): string => app(Platforms::class)->for($platform)->isConfigured() ? 'success' : ($platform->isDemo() ? 'gray' : 'danger')))
                    ->all()),

            Section::make('Ochrana před přetížením')
                ->description('Čísla se stahují jednou denně v 6:00, tři dotazy na účet (stav účtu, čísla, dosah). Ruční načtení u klienta má pauzu 30 minut. Když platforma hlásí vytížení nebo omezí dotazy, stahování se samo pozastaví do zítřejšího rána.')
                ->columns(3)
                ->schema(collect(['meta' => 'Meta', 'google_ads' => 'Google Ads', 'ga4' => 'GA4'])->map(fn (string $label, string $key): TextEntry => TextEntry::make('guard_'.$key)
                    ->label($label)
                    ->state(function () use ($key): string {
                        $guard = ApiGuard::for($key);
                        $until = $guard->pausedUntil();

                        return $until
                            ? 'Pozastaveno do '.$until->format('j. n. H:i')
                            : 'Dnes '.$guard->callsToday().' dotazů z bezpečnostního stropu '.$guard->dailyLimit();
                    })
                    ->color(fn (): string => ApiGuard::for($key)->pausedUntil() ? 'danger' : 'gray'))
                    ->values()
                    ->all()),

            Section::make('Ranní souhrn')
                ->schema([
                    TagsInput::make('digest_recipients')
                        ->label('Komu chodí e-mail')
                        ->placeholder('pavel@taveo.cz')
                        ->helperText('Prázdné = všem účtům. Chodí ve všední dny v 7:15, jen když je co hlásit.'),
                ]),

            Section::make('Kdy upozornit')
                ->description('Platí pro všechny klienty. Cílovou cenu za konverzi, ROAS a rozpočet má každý klient vlastní.')
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('budget_under_pct')->label('Pomalé čerpání pod')->numeric()->required()->suffix('% plánu'),
                        TextInput::make('budget_over_pct')->label('Přečerpání nad')->numeric()->required()->suffix('% plánu'),
                        TextInput::make('spend_spike_pct')->label('Skok útraty den na den')->numeric()->required()->suffix('%'),
                        TextInput::make('cpa_over_pct')->label('Cena za konverzi nad cílem o')->numeric()->required()->suffix('%'),
                        TextInput::make('roas_under_pct')->label('ROAS pod cílem o')->numeric()->required()->suffix('%'),
                        TextInput::make('min_conversions')->label('Cenu a ROAS hodnotit od')->numeric()->required()->suffix('konverzí')
                            ->helperText('Za týden. U menšího počtu dělá jedna objednávka desítky procent.'),
                        TextInput::make('ctr_drop_pct')->label('Pokles CTR proti minulému týdnu')->numeric()->required()->suffix('%'),
                        TextInput::make('frequency_max')->label('Frekvence za týden od')->numeric()->step(0.1)->required(),
                        TextInput::make('no_conversion_days')->label('Dny útraty bez konverze')->numeric()->minValue(1)->required()->suffix('dní'),
                    ]),
                ]),

            Section::make('Poslední synchronizace')
                ->collapsible()
                ->schema([
                    TextEntry::make('runs')
                        ->hiddenLabel()
                        ->state(fn (): HtmlString => $this->syncLog()),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test')
                ->label('Otestovat spojení')
                ->icon(Heroicon::OutlinedSignal)
                ->color('gray')
                ->action(function (Platforms $platforms, AccountDirectory $directory): void {
                    // Test se ptá každé platformy na seznam účtů. Jednou za pět minut stačí.
                    if (! Cache::add('ads.connection-test', true, now()->addMinutes(5))) {
                        Notification::make()->warning()->title('Spojení se testovalo před chvílí')->body('Zkuste to znovu za pár minut.')->send();

                        return;
                    }

                    $lines = [];
                    $failed = false;

                    foreach ($platforms->configured() as $platform) {
                        try {
                            $directory->forget($platform);
                            $lines[] = $platform->shortLabel().': vidí '.$platforms->for($platform)->accounts()->count().' účtů';
                        } catch (AdsApiException $e) {
                            $failed = true;
                            $lines[] = $platform->shortLabel().': '.$e->getMessage();
                        }
                    }

                    Notification::make()
                        ->status($failed ? 'danger' : 'success')
                        ->title($lines === [] ? 'Není nastavený žádný přístup' : ($failed ? 'Něco nefunguje' : 'Spojení funguje'))
                        ->body(implode("\n", $lines))
                        ->persistent($failed)
                        ->send();
                }),
        ];
    }

    private function platformState(AdPlatform $platform): string
    {
        $configured = app(Platforms::class)->for($platform)->isConfigured();

        return match ($platform) {
            AdPlatform::Meta => $configured ? 'Token je nastavený.' : 'Chybí META_SYSTEM_USER_TOKEN.',
            AdPlatform::GoogleAds => $configured ? 'Přístup je nastavený (MCC '.config('ads.google_ads.login_customer_id').').' : 'Chybí GOOGLE_ADS_CLIENT_ID, _SECRET, _REFRESH_TOKEN nebo _LOGIN_CUSTOMER_ID.',
            AdPlatform::Ga4 => $configured ? 'Servisní účet '.app(Ga4::class)->serviceAccountEmail().'. Ten klient přidá do GA4 jako čtenáře.' : 'Chybí GA4_CREDENTIALS.',
            AdPlatform::Demo, AdPlatform::DemoGa4 => $configured ? 'Zapnuté. Po napojení skutečných účtů vypněte (ADS_DEMO=false) a smažte: php artisan ads:demo --remove' : 'Vypnuté.',
        };
    }

    /** Posledních 15 běhů jako jednoduchý seznam. */
    private function syncLog(): HtmlString
    {
        $runs = AdSyncRun::query()->with('account.client')->latest('started_at')->limit(15)->get();

        if ($runs->isEmpty()) {
            return new HtmlString('<p class="text-sm text-gray-500">Zatím nic. Synchronizace běží každé ráno v 6:00.</p>');
        }

        $rows = $runs->map(fn (AdSyncRun $run): string => '<li class="flex flex-wrap justify-between gap-2 py-1.5">'
            .'<span>'.e($run->account?->client?->name.' · '.$run->account?->name).'</span>'
            .'<span class="'.match ($run->status) {
                'ok' => 'text-success-600', 'skipped' => 'text-gray-500', default => 'text-danger-600'
            }.'">'
            .e($run->started_at->format('j. n. H:i').' · '.($run->status === 'ok' ? $run->rows.' řádků' : ($run->error ?? $run->status)))
            .'</span></li>')->implode('');

        return new HtmlString('<ul class="divide-y divide-gray-100 text-sm dark:divide-white/5">'.$rows.'</ul>');
    }
}
