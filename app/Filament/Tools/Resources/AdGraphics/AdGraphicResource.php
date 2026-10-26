<?php

namespace App\Filament\Tools\Resources\AdGraphics;

use App\Filament\Tools\Pages\Ads\ManageAdGraphics;
use App\Filament\Tools\Resources\AdGraphics\Pages\CreateAdGraphic;
use App\Filament\Tools\Resources\AdGraphics\Pages\ListAdGraphics;
use App\Filament\Tools\Resources\AdGraphics\Pages\ViewAdGraphic;
use App\Models\AdGraphic;
use App\Models\Client;
use App\Settings\AdGraphicsSettings;
use App\Support\AdGraphics\Formats;
use App\Support\Agent\AgentServer;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

/**
 * Reklamní grafika od agenta: zadání (formáty, styl, podklady) → agent na
 * pozadí projde web klienta a připraví návrhy jako PNG a ve Figmě.
 * Úpravy hotových návrhů jsou další kola téhož zadání. Viz docs/AGENT.md.
 */
class AdGraphicResource extends Resource
{
    protected static ?string $model = AdGraphic::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $navigationLabel = 'Reklamní grafika';

    protected static ?string $modelLabel = 'grafika';

    protected static ?string $pluralModelLabel = 'Reklamní grafika';

    protected static string|\UnitEnum|null $navigationGroup = 'Reklamy';

    protected static ?int $navigationSort = 50;

    protected static ?string $slug = 'reklamy/grafika';

    protected static ?string $recordTitleAttribute = 'title';

    protected static bool $hasTitleCaseModelLabel = false;

    /** V seznamu jen první zadání, úpravy jsou v jeho detailu. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNull('parent_id');
    }

    public static function getNavigationBadge(): ?string
    {
        $running = AdGraphic::query()->whereIn('status', [AdGraphic::STATUS_QUEUED, AdGraphic::STATUS_RUNNING])->count();

        return $running > 0 ? (string) $running : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Agent na nich právě pracuje';
    }

    public static function form(Schema $schema): Schema
    {
        $imagesEnabled = app(AdGraphicsSettings::class)->imagesEnabled();

        return $schema->components([
            Section::make('Co chcete')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Select::make('client_id')
                        ->label('Klient')
                        ->options(fn (): array => Client::query()->active()->whereNotNull('agent_project')->where('agent_project', '!=', '')->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (?string $state, Set $set): void {
                            $client = $state ? Client::find($state) : null;

                            if ($client) {
                                $set('project', $client->agent_project);
                                $set('website_url', $client->website_url);
                            }
                        })
                        ->helperText('Jen klienti s projektem agenta. Agent si projde jejich web.'),
                    Select::make('project')
                        ->label('Projekt agenta')
                        ->options(fn (): array => self::projects())
                        ->required()
                        ->helperText('Kde agent pracuje a kam uloží návrhy. U klienta se vyplní sám.'),
                    TextInput::make('title')
                        ->label('Název')
                        ->placeholder('Modrý čaj na podzim')
                        ->required()
                        ->maxLength(120),
                    Select::make('variants')
                        ->label('Počet návrhů')
                        ->options(array_combine(range(1, 8), range(1, 8)))
                        ->default(5)
                        ->required(),
                    Textarea::make('brief')
                        ->label('Co propagujeme a co má reklama říct')
                        ->placeholder('Modrý čaj z motýlího květu. Ukázat, že po citronu zfialoví. Cílovka ženy 25–45, Instagram. Cena od 99 Kč.')
                        ->helperText('Produkt nebo akce, komu je reklama určená, hlavní sdělení, cena, výzva k akci. Co nevíte, agent dohledá na webu.')
                        ->rows(5)
                        ->required()
                        ->maxLength(5000)
                        ->columnSpanFull(),
                ]),

            Section::make('Formáty')
                ->columnSpanFull()
                ->schema([
                    CheckboxList::make('presets')
                        ->label('Kam to půjde')
                        ->options(Formats::options())
                        ->default(['ig_post'])
                        ->columns(2),
                    Repeater::make('custom_formats')
                        ->label('Vlastní rozměr')
                        ->schema([
                            TextInput::make('label')->label('Název')->placeholder('Banner do newsletteru'),
                            TextInput::make('width')->label('Šířka')->numeric()->minValue(50)->maxValue(4000)->suffix('px')->required(),
                            TextInput::make('height')->label('Výška')->numeric()->minValue(50)->maxValue(4000)->suffix('px')->required(),
                        ])
                        ->columns(3)
                        ->defaultItems(0)
                        ->addActionLabel('Přidat vlastní rozměr'),
                    Toggle::make('every_format')
                        ->label('Každý návrh ve všech vybraných formátech')
                        ->helperText('Vypnuté: formáty se rozdělí mezi návrhy. Zapnuté: z každého návrhu vznikne sada do všech formátů (víc práce, trvá déle).'),
                ]),

            Section::make('Styl')
                ->columnSpanFull()
                ->description('Prázdné = agent styl odvodí z webu klienta.')
                ->columns(2)
                ->schema([
                    TextInput::make('website_url')
                        ->label('Web klienta')
                        ->url()
                        ->placeholder('https://www.svetcejlonu.cz'),
                    TextInput::make('fonts')
                        ->label('Písma')
                        ->placeholder('Lora na nadpisy, Source Sans 3 na text')
                        ->maxLength(255),
                    Repeater::make('colors')
                        ->label('Barvy')
                        ->simple(ColorPicker::make('value'))
                        ->defaultItems(0)
                        ->addActionLabel('Přidat barvu')
                        ->grid(3),
                    Textarea::make('inspiration')
                        ->label('Inspirace a co ano, co ne')
                        ->placeholder('Odkazy na reklamy, které se líbí. Hodně fotky, málo textu. Žádné emoji.')
                        ->rows(4)
                        ->maxLength(3000),
                ]),

            Section::make('Podklady')
                ->columnSpanFull()
                ->schema([
                    FileUpload::make('asset_paths')
                        ->label('Logo, fotky, ukázky')
                        ->multiple()
                        ->disk('local')
                        ->directory('reklamni-grafika')
                        ->visibility('private')
                        ->storeFileNamesIn('asset_names')
                        ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml', 'application/pdf'])
                        ->maxFiles(10)
                        ->maxSize(20 * 1024)
                        ->helperText('PNG, JPG, WebP, SVG nebo PDF, nejvýš 10 souborů po 20 MB. Logo nejlépe ve vektoru (SVG, PDF).'),
                    Toggle::make('ai_images')
                        ->label('Smí si vygenerovat obrázky (Nano Banana)')
                        ->disabled(! $imagesEnabled)
                        ->default($imagesEnabled)
                        ->helperText($imagesEnabled
                            ? 'Fotky a pozadí, ne texty ani logo. Platí se za obrázek (zhruba 1 Kč), na zadání nejvýš '.app(AdGraphicsSettings::class)->max_images.'.'
                            : new HtmlString('Vypnuté, chybí klíč ke Gemini. Vloží se v <a class="underline" href="'.e(ManageAdGraphics::getUrl()).'">Nastavení grafiky</a>.')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['client', 'revisions']))
            ->poll(fn (): ?string => AdGraphic::query()->whereIn('status', [AdGraphic::STATUS_QUEUED, AdGraphic::STATUS_RUNNING])->exists() ? '15s' : null)
            ->columns([
                TextColumn::make('title')
                    ->label('Zadání')
                    ->weight('bold')
                    ->description(fn (AdGraphic $record): string => ($record->client?->name ?? $record->project).' · '.$record->formatsShort())
                    ->wrap()
                    ->searchable(),
                TextColumn::make('outputs')
                    ->label('Návrhy')
                    ->state(fn (AdGraphic $record): string => (string) $record->rounds()->sum(fn (AdGraphic $round): int => count($round->outputs ?? [])))
                    ->description(fn (AdGraphic $record): ?string => $record->revisions->isNotEmpty() ? $record->revisions->count().'× upraveno' : null),
                TextColumn::make('status')
                    ->label('Stav')
                    ->badge()
                    ->state(fn (AdGraphic $record): string => $record->rounds()->last()->statusLabel())
                    ->color(fn (AdGraphic $record): string => $record->rounds()->last()->statusColor()),
                TextColumn::make('author')->label('Zadal')->color('gray'),
                TextColumn::make('created_at')->label('Kdy')->since()->sortable()->color('gray'),
            ])
            ->filters([
                SelectFilter::make('client')->label('Klient')->relationship('client', 'name')->searchable()->preload(),
            ])
            ->recordActions([ViewAction::make()->label('Otevřít')])
            ->recordUrl(fn (AdGraphic $record): string => self::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Zatím žádná grafika')
            ->emptyStateDescription('Zadejte klienta, formáty a co propagovat. Agent projde web klienta a připraví návrhy jako obrázky i ve Figmě.');
    }

    /** @return array<string, string> projekty agenta (při výpadku služby aspoň ty z klientů) */
    public static function projects(): array
    {
        $projects = rescue(fn (): array => collect(AgentServer::fromConfig()->projects())->pluck('slug')->all(), [], report: false);
        $projects = [...$projects, ...Client::query()->whereNotNull('agent_project')->pluck('agent_project')->all()];

        return collect($projects)->filter()->unique()->sort()->mapWithKeys(fn (string $slug): array => [$slug => $slug])->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdGraphics::route('/'),
            'create' => CreateAdGraphic::route('/nova'),
            'view' => ViewAdGraphic::route('/{record}'),
        ];
    }
}
