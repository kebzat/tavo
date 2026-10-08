<?php

namespace App\Filament\Tools\Resources\Clients\RelationManagers;

use App\Enums\TaskStatus;
use App\Enums\WorkArea;
use App\Models\Client;
use App\Models\ClientTask;
use App\Models\User;
use App\Support\Ads\Billing;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

/**
 * Úkoly klienta. Na přehledu pro klienta je vidí podle stavu: hotové
 * a rozpracované v měsíci, „čeká na vás“ a plán po měsících.
 */
class TasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    protected static ?string $title = 'Úkoly';

    protected static ?string $modelLabel = 'úkol';

    protected static ?string $pluralModelLabel = 'úkoly';

    public function form(Schema $schema): Schema
    {
        return $schema->components(self::fields($this->getOwnerRecord()));
    }

    /**
     * Pole úkolu. Sdílí je i rychlé založení úkolu při zápisu času.
     *
     * @return list<mixed>
     */
    public static function fields(?Client $client = null): array
    {
        return [
            TextInput::make('title')
                ->label('Úkol')
                ->required()
                ->maxLength(255)
                ->placeholder('Zrychlit načítání úvodní stránky na mobilu')
                ->columnSpanFull(),
            RichEditor::make('description')
                ->label('Popis pro klienta')
                ->toolbarButtons([['bold', 'italic', 'link'], ['bulletList', 'orderedList'], ['undo', 'redo']])
                ->helperText('Co a proč, jednou dvěma větami. Klient ho vidí na přehledu. Enter = nový odstavec, Shift+Enter = nový řádek.')
                ->columnSpanFull(),
            Grid::make(2)->schema([
                Select::make('area')
                    ->label('Oblast')
                    ->options(WorkArea::class)
                    ->default($client?->retainers->first()?->area ?? WorkArea::Web)
                    ->required(),
                Select::make('status')
                    ->label('Stav')
                    ->options(TaskStatus::class)
                    ->default(TaskStatus::Planned)
                    ->required(),
                DatePicker::make('planned_for')
                    ->label('Měsíc')
                    ->native(false)
                    ->displayFormat('F Y')
                    ->default(now()->startOfMonth())
                    ->helperText('Kdy na úkol dojde. Prázdné = „Později“.'),
                Select::make('user_id')
                    ->label('Kdo vede')
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->default(auth()->id()),
            ]),
            Textarea::make('internal_note')
                ->label('Interní poznámka')
                ->rows(2)
                ->helperText('Vidíme jen my.')
                ->columnSpanFull(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user')->withSum(['timeEntries' => fn (Builder $query) => $query->where('billable', true)], 'minutes'))
            ->defaultSort(fn (Builder $query) => $query
                ->orderByRaw("case status when 'in_progress' then 0 when 'waiting' then 1 when 'planned' then 2 else 3 end")
                ->orderBy('planned_for')
                ->orderByDesc('done_on'))
            ->columns([
                TextColumn::make('title')
                    ->label('Úkol')
                    ->weight('bold')
                    ->wrap()
                    ->searchable()
                    ->description(fn (ClientTask $record): ?HtmlString => $record->descriptionHtml()),
                TextColumn::make('area')->label('Oblast')->badge(),
                TextColumn::make('status')->label('Stav')->badge(),
                TextColumn::make('planned_for')->label('Měsíc')->date('F Y')->placeholder('–')->sortable(),
                TextColumn::make('time_entries_sum_minutes')
                    ->label('Čas')
                    ->formatStateUsing(fn ($state): string => Billing::formatMinutes((int) $state))
                    ->placeholder('–'),
                TextColumn::make('user.name')->label('Kdo')->toggleable(),
                TextColumn::make('done_on')->label('Hotovo')->date('j. n. Y')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Stav')->options(TaskStatus::class),
                SelectFilter::make('area')->label('Oblast')->options(WorkArea::class),
            ])
            ->headerActions([
                CreateAction::make()->label('Přidat úkol'),
                Action::make('dashboard')
                    ->label('Přehled pro klienta')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (): string => $this->getOwnerRecord()->dashboardPreviewUrl(), shouldOpenInNewTab: true),
            ])
            ->recordActions([
                Action::make('done')
                    ->label('Hotovo')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (ClientTask $record): bool => $record->status->isOpen())
                    ->action(fn (ClientTask $record) => $record->update(['status' => TaskStatus::Done])),
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Zapsaný čas zůstane, jen přestane patřit k úkolu.'),
            ])
            ->emptyStateHeading('Žádné úkoly')
            ->emptyStateDescription('Úkoly klient vidí na přehledu spolupráce. Čas se k nim přiřazuje při zápisu.');
    }
}
