<?php

namespace App\Filament\Tools\Resources\Companies\Tables;

use App\Enums\Crm\CompanySegment;
use App\Enums\Crm\CompanySource;
use App\Enums\Crm\CompanyStatus;
use App\Enums\Crm\Priority;
use App\Filament\Tools\Actions\LogActivityAction;
use App\Models\Crm\Company;
use App\Models\Crm\Tag;
use App\Models\User;
use App\Support\Crm\CsvExport;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class CompaniesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['owner', 'tags']))
            // Pořadí pracovního seznamu: co má termín, a z toho nejdřív áčka.
            ->defaultSort(fn (Builder $query) => $query->workOrder())
            ->persistFiltersInSession()
            ->columns([
                TextColumn::make('name')
                    ->label('Firma')
                    ->searchable()
                    ->weight('bold')
                    ->wrap()
                    ->extraHeaderAttributes(['style' => 'min-width: 14rem'])
                    ->description(fn (Company $record): ?string => collect([$record->city, $record->industry])
                        ->filter()
                        ->join(' • ') ?: null)
                    // Propásnutý follow-up musí být vidět na první pohled,
                    // ne až po přečtení sloupce s datem.
                    ->icon(fn (Company $record): ?Heroicon => $record->isOverdue() ? Heroicon::ExclamationCircle : null)
                    ->iconColor('danger'),

                TextColumn::make('domain')
                    ->label('Web')
                    ->searchable(['website', 'domain'])
                    ->url(fn (Company $record): ?string => $record->websiteUrl(), shouldOpenInNewTab: true)
                    ->color('primary')
                    ->limit(28)
                    ->toggleable(),

                TextColumn::make('status')->label('Stav')->badge(),

                TextColumn::make('priority')
                    ->label('Priorita')
                    ->badge()
                    ->formatStateUsing(fn (Priority $state): string => $state->short())
                    ->sortable(),

                TextColumn::make('segment')
                    ->label('Segment')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('platform')
                    ->label('Platforma')
                    ->toggleable()
                    ->searchable(),

                TextColumn::make('owner.name')
                    ->label('Vede')
                    ->toggleable()
                    ->placeholder('—'),

                TextColumn::make('next_action_at')
                    ->label('Další krok')
                    ->dateTime('j. n. Y')
                    ->sortable()
                    ->placeholder('—')
                    ->color(fn (Company $record): ?string => $record->isOverdue() ? 'danger' : null)
                    ->weight(fn (Company $record): ?string => $record->isOverdue() ? 'bold' : null),

                TextColumn::make('last_activity_at')
                    ->label('Poslední aktivita')
                    ->since()
                    ->sortable()
                    ->placeholder('nikdy')
                    ->toggleable(),

                TextColumn::make('tags.name')
                    ->label('Štítky')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('segment')->label('Segment')->options(CompanySegment::class)->multiple(),
                SelectFilter::make('priority')->label('Priorita')->options(Priority::class)->multiple(),
                SelectFilter::make('status')->label('Stav')->options(CompanyStatus::class)->multiple(),
                SelectFilter::make('source')->label('Zdroj')->options(CompanySource::class)->multiple(),

                SelectFilter::make('owner_id')
                    ->label('Vede')
                    ->options(fn (): array => User::orderBy('name')->pluck('name', 'id')->all()),

                SelectFilter::make('platform')
                    ->label('Platforma')
                    // Platforma je volný text, ne číselník. Nabídka se proto
                    // staví z toho, co v datech opravdu je.
                    ->options(fn (): array => Company::query()
                        ->whereNotNull('platform')
                        ->distinct()
                        ->orderBy('platform')
                        ->pluck('platform', 'platform')
                        ->all()),

                SelectFilter::make('tags')
                    ->label('Štítek')
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->preload(),

                Filter::make('overdue')
                    ->label('Follow-up po termínu')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->overdue()),

                Filter::make('stale')
                    ->label('Bez aktivity 7+ dní')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->stale()),
            ])
            ->recordActions([
                LogActivityAction::make()
                    ->label('')
                    ->tooltip('Zalogovat aktivitu')
                    ->iconButton(),
                EditAction::make()->label('')->iconButton()->tooltip('Otevřít'),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Export CSV')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    // Exportuje se to, co je zrovna vyfiltrované — jinak by se
                    // z filtrů stala jen ozdoba seznamu.
                    ->action(fn ($livewire) => self::export($livewire->getFilteredSortedTableQuery())),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('setStatus')
                        ->label('Změnit stav')
                        ->icon(Heroicon::OutlinedFlag)
                        ->schema([
                            Select::make('status')
                                ->label('Nový stav')
                                ->options(CompanyStatus::class)
                                ->native(false)
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records->each->update(['status' => $data['status']]);

                            self::done($records->count().' firem má nový stav.');
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('setOwner')
                        ->label('Změnit, kdo vede')
                        ->icon(Heroicon::OutlinedUser)
                        ->schema([
                            Select::make('owner_id')
                                ->label('Vede')
                                ->options(fn (): array => User::orderBy('name')->pluck('name', 'id')->all())
                                ->native(false)
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records->each->update(['owner_id' => $data['owner_id']]);

                            self::done($records->count().' firem má nového vlastníka.');
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('addTag')
                        ->label('Přidat štítek')
                        ->icon(Heroicon::OutlinedTag)
                        ->schema([
                            Select::make('tag_id')
                                ->label('Štítek')
                                ->options(fn (): array => Tag::orderBy('name')->pluck('name', 'id')->all())
                                ->native(false)
                                ->required()
                                ->createOptionForm([
                                    TextInput::make('name')
                                        ->label('Název')
                                        ->required()
                                        ->unique(Tag::class, 'name'),
                                ])
                                ->createOptionUsing(fn (array $data): int => Tag::create($data)->getKey()),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            // syncWithoutDetaching, ať se už přiřazené štítky
                            // neodeberou a přiřazení dvakrát nespadne na klíči.
                            $records->each(fn (Company $company) => $company->tags()->syncWithoutDetaching([$data['tag_id']]));

                            self::done('Štítek přidán k '.$records->count().' firmám.');
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('setFollowUp')
                        ->label('Nastavit follow-up')
                        ->icon(Heroicon::OutlinedBellAlert)
                        ->schema([
                            DatePicker::make('follow_up_at')
                                ->label('Kdy se ozvat')
                                ->default(now()->addDays(3))
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $when = Carbon::parse($data['follow_up_at'])->setTime(9, 0);

                            $records->each(fn (Company $company) => $company->scheduleFollowUp($when));

                            self::done('Follow-up nastaven u '.$records->count().' firem.');
                        })
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Zatím žádné firmy')
            ->emptyStateDescription('Naimportuj tabulku z rešerše přes CRM → Import firem, nebo přidej první firmu ručně.')
            ->emptyStateIcon(Heroicon::OutlinedBuildingOffice2);
    }

    /** Stejná hlavička jako u importu, doplněná o to, co CRM přidalo. */
    public static function export(Builder $query)
    {
        $header = [
            'segment', 'firma', 'mesto', 'obor', 'web', 'platforma', 'bolest', 'balicek', 'reference', 'kontakt', 'priorita',
            'stav', 'vede', 'dalsi_krok', 'posledni_aktivita',
        ];

        $rows = $query->with(['owner', 'contacts'])->get()->map(function (Company $company): array {
            $contact = $company->contacts->firstWhere('is_primary', true) ?? $company->contacts->first();

            return [
                $company->segment,
                $company->name,
                $company->city,
                $company->industry,
                $company->website,
                $company->platform,
                $company->pain,
                $company->offer,
                $company->reference_to_use,
                collect([$contact?->name, $contact?->email, $contact?->phone])->filter()->join(', '),
                $company->priority->short(),
                $company->status,
                $company->owner?->name,
                $company->next_action_at,
                $company->last_activity_at,
            ];
        });

        return CsvExport::download('firmy-'.now()->format('Y-m-d').'.csv', $header, $rows);
    }

    private static function done(string $message): void
    {
        Notification::make()->success()->title($message)->send();
    }
}
