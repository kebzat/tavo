<?php

namespace App\Filament\Tools\Resources\AdReports;

use App\Enums\Ads\ReportStatus;
use App\Enums\Ads\ReportType;
use App\Filament\Tools\Actions\Reviews;
use App\Filament\Tools\Resources\AdReports\Pages\CreateAdReport;
use App\Filament\Tools\Resources\AdReports\Pages\EditAdReport;
use App\Filament\Tools\Resources\AdReports\Pages\ListAdReports;
use App\Models\Ads\AdReport;
use App\Models\Client;
use App\Support\Ads\Period;
use App\Support\Ads\ReportBuilder;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Týdenní a měsíční reporty reklam pro klienty. Koncepty zakládá pondělní
 * běh (ads:reports) nebo tlačítko na detailu klienta. Pavel doplní komentář,
 * zkontroluje náhled a odešle.
 */
class AdReportResource extends Resource
{
    protected static ?string $model = AdReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?string $navigationLabel = 'Reporty';

    protected static ?string $modelLabel = 'report';

    protected static ?string $pluralModelLabel = 'Reporty';

    protected static string|\UnitEnum|null $navigationGroup = 'Reklamy';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'reklamy/reporty';

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationBadge(): ?string
    {
        $drafts = AdReport::query()->where('status', ReportStatus::Draft)->count();

        return $drafts > 0 ? (string) $drafts : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Koncepty čekající na komentář a odeslání';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            // Při zakládání se vybírá jen klient a období, čísla se spočítají sama.
            Section::make('Za jaké období')
                ->visibleOn('create')
                ->schema([
                    Select::make('client_id')
                        ->label('Klient')
                        ->options(fn (): array => Client::query()->withAds()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->required(),
                    Select::make('type')
                        ->label('Typ')
                        ->options(ReportType::class)
                        ->default(ReportType::Weekly->value)
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set): void {
                            // Select s enumem vrací podle okolností instanci i hodnotu.
                            $period = match ($state instanceof ReportType ? $state : ReportType::tryFrom((string) $state)) {
                                ReportType::Monthly => Period::month(now()->subMonthNoOverflow()),
                                ReportType::Weekly => Period::week(now()->subWeek()),
                                default => null,
                            };

                            if ($period) {
                                $set('period_start', $period->from->toDateString());
                                $set('period_end', $period->to->toDateString());
                            }
                        }),
                    Grid::make(2)->schema([
                        DatePicker::make('period_start')->label('Od')->native(false)->displayFormat('j. n. Y')->required()
                            ->default(fn (): string => Period::week(now()->subWeek())->from->toDateString()),
                        DatePicker::make('period_end')->label('Do')->native(false)->displayFormat('j. n. Y')->required()->afterOrEqual('period_start')
                            ->default(fn (): string => Period::week(now()->subWeek())->to->toDateString()),
                    ]),
                ]),

            Section::make('Report')
                ->visibleOn('edit')
                ->schema([
                    TextInput::make('title')->label('Nadpis')->required(),
                    MarkdownEditor::make('summary')
                        ->label('Náš komentář')
                        ->helperText('Co jsme za období udělali, co z čísel plyne a co chystáme dál. Klient ho čte nad čísly. Krátké odstavce, bez žargonu.')
                        ->toolbarButtons(['bold', 'italic', 'bulletList', 'orderedList', 'link', 'undo', 'redo']),
                ]),

            Section::make('Sdílení')
                ->visibleOn('edit')
                ->columns(2)
                ->schema([
                    Toggle::make('is_public')
                        ->label('Odkaz funguje i bez přihlášení')
                        ->helperText('Při odeslání e-mailem se zapne samo.'),
                    TextEntry::make('recipients')
                        ->label('Odeslání půjde na')
                        ->state(fn (?AdReport $record): string => $record
                            ? (implode(', ', app(ReportBuilder::class)->recipients($record->client)) ?: 'Nikoho, doplňte e-mail u klienta (Cíle a paušál).')
                            : ''),
                    TextEntry::make('sent')
                        ->label('Odesláno')
                        ->state(fn (?AdReport $record): string => $record?->sent_at
                            ? $record->sent_at->format('j. n. Y H:i').' na '.implode(', ', $record->sent_to ?? [])
                            : 'Zatím ne'),
                    TextEntry::make('views')
                        ->label('Otevřeno')
                        ->state(fn (?AdReport $record): string => $record?->viewSummary('Klient report zatím neotevřel.') ?? ''),
                    Reviews::entry(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('period_end', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['client', 'reviews']))
            ->columns([
                TextColumn::make('title')
                    ->label('Report')
                    ->weight('bold')
                    ->description(fn (AdReport $record): string => $record->client->name)
                    ->searchable(),
                TextColumn::make('type')->label('Typ')->badge()->color('gray'),
                TextColumn::make('period_end')->label('Do')->date('j. n. Y')->sortable(),
                TextColumn::make('status')->label('Stav')->badge(),
                ...Reviews::columns(),
                TextColumn::make('view_count')
                    ->label('Otevřeno')
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? $state.'×' : 'ne')
                    ->description(fn (AdReport $record): ?string => $record->last_viewed_at?->diffForHumans()),
            ])
            ->filters([
                SelectFilter::make('status')->label('Stav')->options(ReportStatus::class),
                SelectFilter::make('client')->label('Klient')->relationship('client', 'name')->searchable()->preload(),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Náhled')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->url(fn (AdReport $record): string => $record->previewUrl(), shouldOpenInNewTab: true),
                EditAction::make(),
            ])
            ->emptyStateHeading('Zatím žádný report')
            ->emptyStateDescription('Koncepty týdenních reportů vznikají každé pondělí ráno, měsíčních prvního v měsíci.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdReports::route('/'),
            'create' => CreateAdReport::route('/create'),
            'edit' => EditAdReport::route('/{record}/edit'),
        ];
    }
}
