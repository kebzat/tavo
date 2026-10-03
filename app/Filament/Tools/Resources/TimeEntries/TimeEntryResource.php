<?php

namespace App\Filament\Tools\Resources\TimeEntries;

use App\Filament\Tools\Actions\Ads\LogTimeAction;
use App\Filament\Tools\Resources\TimeEntries\Pages\ManageTimeEntries;
use App\Models\TimeEntry;
use App\Support\Ads\Billing;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/** Odpracovaný čas u klientů. Nad hodiny v paušálu se fakturuje, viz Fakturace. */
class TimeEntryResource extends Resource
{
    protected static ?string $model = TimeEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'Hodiny';

    protected static ?string $modelLabel = 'záznam času';

    protected static ?string $pluralModelLabel = 'Hodiny';

    protected static string|\UnitEnum|null $navigationGroup = 'Reklamy';

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'reklamy/hodiny';

    public static function form(Schema $schema): Schema
    {
        return $schema->components(LogTimeAction::schema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('worked_on', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['client', 'user', 'task']))
            ->columns([
                TextColumn::make('worked_on')->label('Den')->date('j. n. Y')->sortable(),
                TextColumn::make('client.name')->label('Klient')->searchable()->sortable(),
                TextColumn::make('description')->label('Co se dělalo')->wrap()->searchable()
                    ->description(fn (TimeEntry $record): ?string => $record->task?->title),
                TextColumn::make('area')->label('Oblast')->badge()->toggleable(),
                TextColumn::make('minutes')->label('Čas')->formatStateUsing(fn (int $state): string => Billing::formatMinutes($state))->sortable()
                    ->summarize(Sum::make()->label('Celkem')->formatStateUsing(fn ($state): string => Billing::formatMinutes((int) $state))),
                TextColumn::make('user.name')->label('Kdo')->toggleable(),
                IconColumn::make('billable')->label('Fakturovat')->boolean(),
                TextColumn::make('invoiced_at')->label('Vyfakturováno')->date('j. n. Y')->placeholder('ne'),
            ])
            ->filters([
                SelectFilter::make('month')
                    ->label('Měsíc')
                    ->options(fn (): array => collect(range(0, 11))
                        ->mapWithKeys(fn (int $back): array => [
                            now()->startOfMonth()->subMonthsNoOverflow($back)->format('Y-m') => now()->startOfMonth()->subMonthsNoOverflow($back)->translatedFormat('F Y'),
                        ])->all())
                    ->default(now()->format('Y-m'))
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->inMonth(Carbon::parse($data['value'].'-01')->year, Carbon::parse($data['value'].'-01')->month)
                        : $query),
                SelectFilter::make('client')->label('Klient')->relationship('client', 'name')->searchable()->preload(),
                TernaryFilter::make('invoiced')
                    ->label('Vyfakturováno')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('invoiced_at'),
                        false: fn (Builder $query) => $query->whereNull('invoiced_at'),
                    ),
                Filter::make('billable')->label('Jen fakturovatelné')->query(fn (Builder $query) => $query->where('billable', true)),
            ])
            ->recordActions([
                EditAction::make()
                    ->fillForm(fn (TimeEntry $record): array => [
                        'client_id' => $record->client_id,
                        'worked_on' => $record->worked_on,
                        'duration' => intdiv($record->minutes, 60).':'.str_pad((string) ($record->minutes % 60), 2, '0', STR_PAD_LEFT),
                        'task_id' => $record->task_id,
                        'area' => $record->area,
                        'description' => $record->description,
                        'billable' => $record->billable,
                    ])
                    ->using(function (TimeEntry $record, array $data): TimeEntry {
                        $record->update([
                            'client_id' => $data['client_id'],
                            'worked_on' => $data['worked_on'],
                            'minutes' => Billing::parseMinutes((string) $data['duration']),
                        ] + LogTimeAction::attributes($data));

                        return $record;
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('invoice')
                        ->label('Označit jako vyfakturované')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->action(fn (Collection $records) => TimeEntry::query()->whereKey($records->modelKeys())->update(['invoiced_at' => now()]))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Žádný zapsaný čas')
            ->emptyStateDescription('Čas se zapisuje tlačítkem nahoře nebo na detailu klienta.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTimeEntries::route('/'),
        ];
    }
}
