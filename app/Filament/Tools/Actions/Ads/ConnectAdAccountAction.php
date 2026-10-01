<?php

namespace App\Filament\Tools\Actions\Ads;

use App\Enums\Ads\AdPlatform;
use App\Enums\Ads\PrimaryGoal;
use App\Filament\Tools\Pages\Ads\AdsClient;
use App\Jobs\BackfillAdAccount;
use App\Models\Client;
use App\Support\Ads\AccountDirectory;
use App\Support\Ads\Platforms\Platforms;
use App\Support\UniqueSlug;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;

/**
 * Propojení reklamního účtu s klientem. Na přehledu zakládá nového klienta
 * (nebo vybere existujícího), na detailu klienta přidává další účet.
 *
 * Účet se vybírá ze seznamu toho, co nám klienti nasdíleli, nic se
 * neopisuje. Po propojení se na pozadí stáhne historie.
 */
class ConnectAdAccountAction
{
    public static function make(?Client $client = null): Action
    {
        $directory = app(AccountDirectory::class);
        $platforms = app(Platforms::class);
        $platformOf = fn ($state): ?AdPlatform => $state instanceof AdPlatform ? $state : AdPlatform::tryFrom((string) $state);

        return Action::make($client ? 'connectAccount' : 'addClient')
            ->label($client ? 'Propojit účet' : 'Přidat klienta')
            ->icon(Heroicon::OutlinedPlus)
            ->color($client ? 'gray' : 'primary')
            ->modalHeading($client ? 'Propojit další účet' : 'Přidat klienta do reklam')
            ->modalDescription('Klient nejdřív nasdílí účet Taveo: reklamní účet Meta Business Manageru Taveo, účet Google Ads pod MCC Taveo, GA4 property e-mailu našeho servisního účtu. Pak se tu objeví v nabídce.')
            ->modalSubmitActionLabel('Propojit')
            ->fillForm(fn (): array => [
                // Skutečná platforma před ukázkovou, když je nastavená.
                'platform' => (collect($platforms->configured())->first(fn (AdPlatform $platform): bool => ! $platform->isDemo())
                    ?? $platforms->configured()[0] ?? AdPlatform::Meta)->value,
                'primary_goal' => PrimaryGoal::Purchases->value,
            ])
            ->schema(fn (): array => [
                Select::make('client_id')
                    ->label('Klient')
                    ->options(fn (): array => Client::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->required()
                    ->createOptionForm([
                        TextInput::make('name')->label('Název')->required(),
                        TextInput::make('website_url')->label('Web')->url()->placeholder('https://'),
                        TextInput::make('contact_email')->label('E-mail pro reporty')->email(),
                    ])
                    ->createOptionUsing(fn (array $data): int => Client::create([
                        ...$data,
                        'slug' => UniqueSlug::for(new Client, $data['name']),
                    ])->getKey())
                    ->visible($client === null),

                Select::make('platform')
                    ->label('Odkud')
                    ->options(fn (): array => collect($platforms->configured())->mapWithKeys(fn (AdPlatform $platform): array => [$platform->value => $platform->getLabel()])->all())
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (callable $set) => $set('external_id', null))
                    ->helperText(fn (): ?string => $platforms->configured() === []
                        ? 'Není nastavený žádný přístup. Postup je v docs/ADS.md.'
                        : null),

                Select::make('external_id')
                    ->label('Účet')
                    ->options(fn (callable $get): array => ($platform = $platformOf($get('platform'))) ? $directory->options($platform) : [])
                    ->searchable()
                    ->required()
                    ->helperText(fn (callable $get): ?string => ($platform = $platformOf($get('platform'))) ? $directory->problem($platform) : null),

                Section::make('Co s klientem hlídáme')
                    ->description('Jde změnit kdykoli na detailu klienta. Bez cílů se nehlídá cena za konverzi ani rozpočet.')
                    ->visible(fn (callable $get): bool => $client?->adSettings === null && ! $platformOf($get('platform'))?->isAnalytics())
                    ->schema([
                        Select::make('primary_goal')
                            ->label('Hlavní cíl')
                            ->options(PrimaryGoal::class)
                            ->default(PrimaryGoal::Purchases->value)
                            ->required(),
                        Grid::make(3)->schema([
                            TextInput::make('monthly_budget')->label('Rozpočet na měsíc')->numeric()->minValue(0)->suffix('Kč'),
                            TextInput::make('target_cpa')->label('Cílová cena za konverzi')->numeric()->minValue(0)->suffix('Kč'),
                            TextInput::make('target_roas')->label('Cílový ROAS')->numeric()->minValue(0)->step(0.1)->placeholder('4'),
                        ]),
                        TextInput::make('fee_czk')->label('Náš paušál')->numeric()->minValue(0)->suffix('Kč / měsíc')->helperText('Jen pro nás, klient ho v reportu nevidí.'),
                    ]),
            ])
            ->action(function (array $data) use ($client, $directory, $platformOf): void {
                $client ??= Client::findOrFail($data['client_id']);
                $platform = $platformOf($data['platform']);
                $info = $directory->find($platform, $data['external_id']);

                $account = $client->adAccounts()->create([
                    'platform' => $platform,
                    'external_id' => $data['external_id'],
                    'name' => $info?->name ?? 'Účet '.$data['external_id'],
                    'currency' => $info?->currency ?: 'CZK',
                    'timezone' => $info?->timezone,
                    'status' => $info?->status,
                ]);

                if ($client->adSettings()->doesntExist()) {
                    $client->adSettings()->create([
                        'primary_goal' => $data['primary_goal'] ?? PrimaryGoal::Purchases->value,
                        'monthly_budget' => $data['monthly_budget'] ?? null,
                        'target_cpa' => $data['target_cpa'] ?? null,
                        'target_roas' => $data['target_roas'] ?? null,
                        'fee_czk' => $data['fee_czk'] ?? null,
                    ]);
                }

                $directory->forget($platform);
                BackfillAdAccount::dispatchAfterResponse($account->getKey());

                Notification::make()
                    ->success()
                    ->title("{$account->name} je propojený s klientem {$client->name}")
                    ->body('Historie za '.config('ads.backfill_months').' měsíce se stahuje na pozadí. Za minutu obnovte stránku.')
                    ->send();
            })
            ->successRedirectUrl(fn (array $data): string => AdsClient::getUrl(['client' => $client?->getKey() ?? $data['client_id']]));
    }
}
