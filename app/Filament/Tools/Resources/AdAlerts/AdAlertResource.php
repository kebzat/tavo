<?php

namespace App\Filament\Tools\Resources\AdAlerts;

use App\Enums\Ads\AlertSeverity;
use App\Enums\Ads\AlertStatus;
use App\Filament\Tools\Pages\Ads\AdsClient;
use App\Filament\Tools\Resources\AdAlerts\Pages\ListAdAlerts;
use App\Models\Ads\AdAlert;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Schránka upozornění ze všech klientů. Zakládá je denní kontrola
 * (ads:alerts), tady se řeší: Nové / Řeším / Čekám na klienta / Vyřešeno,
 * odložení do data, zamítnutí na týden. Opakovaný problém je jedno
 * upozornění, návrat do normálu zavře upozornění samo.
 */
class AdAlertResource extends Resource
{
    protected static ?string $model = AdAlert::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static ?string $navigationLabel = 'Upozornění';

    protected static ?string $modelLabel = 'upozornění';

    protected static ?string $pluralModelLabel = 'Upozornění';

    protected static string|\UnitEnum|null $navigationGroup = 'Reklamy';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'reklamy/upozorneni';

    public static function getNavigationBadge(): ?string
    {
        $count = AdAlert::query()->open()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return AdAlert::query()->open()->where('severity', AlertSeverity::Critical)->exists() ? 'danger' : 'warning';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['client', 'account', 'group'])->worstFirst())
            ->columns([
                TextColumn::make('severity')->label('')->badge(),
                TextColumn::make('title')
                    ->label('Upozornění')
                    ->weight('bold')
                    ->wrap()
                    ->description(fn (AdAlert $record): string => collect([$record->client->name, $record->group?->name, $record->account?->name])->filter()->implode(' · '))
                    ->searchable(),
                TextColumn::make('facts')
                    ->label('Co se změnilo')
                    ->wrap()
                    ->lineClamp(3)
                    ->description(fn (AdAlert $record): ?string => $record->basis ? 'Z dat: '.$record->basis : null)
                    ->placeholder('–')
                    ->toggleable(),
                TextColumn::make('recommendation')
                    ->label('Co prověřit')
                    ->wrap()
                    ->lineClamp(3)
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('detected_on')
                    ->label('Od')
                    ->date('j. n.')
                    ->description(fn (AdAlert $record): ?string => $record->last_seen_on->isSameDay($record->detected_on) ? null : 'naposledy '.$record->last_seen_on->format('j. n.'))
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Stav')
                    ->badge()
                    ->description(fn (AdAlert $record): ?string => collect([
                        $record->isSnoozed() ? 'odloženo do '.$record->snoozed_until->format('j. n.') : null,
                        $record->waiting_for ? 'čeká se na: '.$record->waiting_for : null,
                        $record->occurrences > 1 ? $record->occurrences.'. výskyt' : null,
                        $record->recovered_on ? 'v normálu od '.$record->recovered_on->format('j. n.') : null,
                    ])->filter()->implode(' · ') ?: null),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stav')
                    ->options(AlertStatus::class)
                    ->multiple()
                    ->default([AlertStatus::Open->value, AlertStatus::Acknowledged->value, AlertStatus::Waiting->value]),
                SelectFilter::make('severity')->label('Závažnost')->options(AlertSeverity::class),
                SelectFilter::make('client')->label('Klient')->relationship('client', 'name')->searchable()->preload(),
            ])
            ->recordUrl(fn (AdAlert $record): string => AdsClient::getUrl(['client' => $record->client_id]))
            ->recordActions([
                ActionGroup::make([
                    self::statusAction('acknowledge', 'Řeším', AlertStatus::Acknowledged, Heroicon::OutlinedHandRaised),
                    Action::make('wait')
                        ->label('Čekám na klienta')
                        ->icon(Heroicon::OutlinedClock)
                        ->visible(fn (AdAlert $record): bool => $record->status !== AlertStatus::Waiting)
                        ->schema([TextInput::make('waiting_for')->label('Na co čekáme')->maxLength(255)])
                        ->action(fn (AdAlert $record, array $data) => $record->moveTo(AlertStatus::Waiting, auth()->user()?->name, $data['waiting_for'] ?? null)),
                    Action::make('snooze')
                        ->label('Odložit')
                        ->icon(Heroicon::OutlinedBellSnooze)
                        ->schema([Select::make('days')->label('Na jak dlouho')->options([1 => 'Do zítřka', 3 => 'Na 3 dny', 7 => 'Na týden', 14 => 'Na 2 týdny'])->default(3)->required()])
                        ->action(fn (AdAlert $record, array $data) => $record->snooze(today()->addDays((int) $data['days']), auth()->user()?->name)),
                    self::statusAction('resolve', 'Vyřešeno', AlertStatus::Resolved, Heroicon::OutlinedCheckCircle),
                    self::statusAction('dismiss', 'Zamítnout na týden', AlertStatus::Dismissed, Heroicon::OutlinedXCircle),
                    self::statusAction('reopen', 'Znovu otevřít', AlertStatus::Open, Heroicon::OutlinedArrowUturnLeft),
                ]),
            ])
            ->emptyStateHeading('Nic nesvítí')
            ->emptyStateDescription('Denní kontrola běží každé ráno po stažení čísel.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdAlerts::route('/'),
        ];
    }

    private static function statusAction(string $name, string $label, AlertStatus $status, Heroicon $icon): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->visible(fn (AdAlert $record): bool => $record->status !== $status)
            ->action(fn (AdAlert $record) => $record->moveTo($status, auth()->user()?->name));
    }
}
