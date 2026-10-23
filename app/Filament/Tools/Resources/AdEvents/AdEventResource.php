<?php

namespace App\Filament\Tools\Resources\AdEvents;

use App\Enums\Ads\AdEventType;
use App\Filament\Tools\Actions\Ads\AdEventForm;
use App\Filament\Tools\Pages\Ads\AdsClient;
use App\Filament\Tools\Resources\AdEvents\Pages\ManageAdEvents;
use App\Models\Ads\AdEvent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Kalendář termínů v reklamách: konce licencí UGC, začátky a konce akcí,
 * příprava Black Friday, konce kampaní, čekání na podklady. Nevyřešený
 * termín visí v ranním přehledu a e-mailu, dokud ho někdo neoznačí.
 */
class AdEventResource extends Resource
{
    protected static ?string $model = AdEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Termíny';

    protected static ?string $modelLabel = 'termín';

    protected static ?string $pluralModelLabel = 'Termíny';

    protected static string|\UnitEnum|null $navigationGroup = 'Reklamy';

    protected static ?int $navigationSort = 25;

    protected static ?string $slug = 'reklamy/terminy';

    public static function getNavigationBadge(): ?string
    {
        $count = AdEvent::query()->due()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return AdEvent::query()->open()->whereDate('due_on', '<', today()->toDateString())->exists() ? 'danger' : 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Termíny, na které se už upozorňuje, nebo jsou po termínu';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(AdEventForm::schema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['client', 'group', 'campaign']))
            ->defaultSort('due_on')
            ->defaultGroup(Group::make('due_on')
                ->label('Měsíc')
                ->getTitleFromRecordUsing(fn (AdEvent $record): string => mb_convert_case($record->due_on->translatedFormat('F Y'), MB_CASE_TITLE))
                ->getKeyFromRecordUsing(fn (AdEvent $record): string => $record->due_on->format('Y-m'))
                ->orderQueryUsing(fn (Builder $query, string $direction) => $query->orderBy('due_on', $direction))
                ->collapsible())
            ->columns([
                TextColumn::make('due_on')
                    ->label('Kdy')
                    ->date('j. n. Y')
                    ->description(fn (AdEvent $record): string => $record->resolved_at ? 'vyřešeno '.$record->resolved_at->format('j. n.') : $record->whenLabel())
                    ->color(fn (AdEvent $record): ?string => $record->isOverdue() ? 'danger' : null)
                    ->sortable(),
                TextColumn::make('type')->label('Druh')->badge(),
                TextColumn::make('title')
                    ->label('Co')
                    ->weight('bold')
                    ->wrap()
                    ->description(fn (AdEvent $record): string => collect([$record->client->name, $record->scopeLabel()])->filter()->implode(' · '))
                    ->searchable(),
                TextColumn::make('note')->label('Poznámka')->wrap()->lineClamp(2)->color('gray')->toggleable(),
                TextColumn::make('remind_days')
                    ->label('Upozornit')
                    ->formatStateUsing(fn (int $state): string => $state === 0 ? 'v den termínu' : $state.' '.($state === 1 ? 'den' : ($state < 5 ? 'dny' : 'dní')).' předem')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('resolved')
                    ->label('Stav')
                    ->placeholder('Všechny')
                    ->trueLabel('Vyřešené')
                    ->falseLabel('Nevyřešené')
                    ->default(false)
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('resolved_at'),
                        false: fn (Builder $query) => $query->whereNull('resolved_at'),
                    ),
                SelectFilter::make('type')->label('Druh')->options(AdEventType::class)->multiple(),
                SelectFilter::make('client')->label('Klient')->relationship('client', 'name')->searchable()->preload(),
            ])
            ->recordActions([
                Action::make('resolve')
                    ->label('Vyřešeno')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (AdEvent $record): bool => $record->resolved_at === null)
                    ->action(fn (AdEvent $record) => $record->update(['resolved_at' => now(), 'resolved_by' => auth()->id()])),
                ActionGroup::make([
                    EditAction::make()
                        ->fillForm(fn (AdEvent $record): array => AdEventForm::fill($record))
                        ->using(fn (AdEvent $record, array $data): AdEvent => AdEventForm::save($data, null, $record)),
                    Action::make('reopen')
                        ->label('Vrátit mezi nevyřešené')
                        ->icon(Heroicon::OutlinedArrowUturnLeft)
                        ->visible(fn (AdEvent $record): bool => $record->resolved_at !== null)
                        ->action(fn (AdEvent $record) => $record->update(['resolved_at' => null, 'resolved_by' => null])),
                    Action::make('client')
                        ->label('Detail klienta')
                        ->icon(Heroicon::OutlinedPresentationChartLine)
                        ->url(fn (AdEvent $record): string => AdsClient::getUrl(['client' => $record->client_id])),
                    DeleteAction::make(),
                ]),
            ])
            ->emptyStateHeading('Žádné termíny')
            ->emptyStateDescription('Přidejte konec licence, začátek akce nebo čekání na podklady. Upozorníme předem.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAdEvents::route('/'),
        ];
    }
}
