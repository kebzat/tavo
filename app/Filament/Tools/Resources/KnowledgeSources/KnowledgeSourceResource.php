<?php

namespace App\Filament\Tools\Resources\KnowledgeSources;

use App\Filament\Tools\Resources\KnowledgeSources\Pages\CreateKnowledgeSource;
use App\Filament\Tools\Resources\KnowledgeSources\Pages\ListKnowledgeSources;
use App\Filament\Tools\Resources\KnowledgeSources\Pages\ViewKnowledgeSource;
use App\Models\Client;
use App\Models\KnowledgeEntry;
use App\Models\KnowledgeSource;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Podklady pro mozek: Pavlovy a Tomovy texty, poznámky ze schůzek, e-maily.
 * Agent (knihovník) je na pozadí přečte a navrhne z nich poznatky ke
 * schválení. Viz docs/MOZEK.md.
 */
class KnowledgeSourceResource extends Resource
{
    protected static ?string $model = KnowledgeSource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?string $navigationLabel = 'Podklady';

    protected static ?string $modelLabel = 'podklady';

    protected static ?string $pluralModelLabel = 'Podklady';

    protected static string|\UnitEnum|null $navigationGroup = 'Mozek';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'mozek/podklady';

    protected static ?string $recordTitleAttribute = 'title';

    protected static bool $hasTitleCaseModelLabel = false;

    /** Vložený text nejvýš tolik znaků (zhruba 150 stran). Víc rozdělte do víc podkladů. */
    public const MAX_TEXT = 300000;

    public static function getNavigationBadge(): ?string
    {
        $running = KnowledgeSource::query()->whereIn('status', [KnowledgeSource::STATUS_QUEUED, KnowledgeSource::STATUS_RUNNING])->count();

        return $running > 0 ? (string) $running : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Agent je právě čte';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Co nahráváte')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextInput::make('title')
                        ->label('Název')
                        ->placeholder('Pavlovy poznámky k reklamám, 2025–2026')
                        ->required()
                        ->maxLength(200),
                    Select::make('client_id')
                        ->label('Klient')
                        ->options(fn (): array => Client::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->placeholder('Bez klienta (firma, víc klientů)')
                        ->helperText('Když se celé podklady týkají jednoho klienta.'),
                    Textarea::make('body')
                        ->label('Text')
                        ->placeholder("Sem vložte cokoli: poznámky, zkušenosti, přepis schůzky, e-maily, postup, jak něco děláte.\nNemusí to být učesané, agent si to roztřídí.")
                        ->helperText('Nejvýš '.number_format(self::MAX_TEXT, 0, ',', ' ').' znaků (asi 150 stran). Víc rozdělte do několika podkladů po tématech, agent je vezme jedny po druhých.')
                        ->rows(16)
                        ->maxLength(self::MAX_TEXT)
                        ->requiredWithout('file_paths')
                        ->columnSpanFull(),
                    FileUpload::make('file_paths')
                        ->label('Nebo soubory')
                        ->multiple()
                        ->disk('local')
                        ->directory('mozek')
                        ->visibility('private')
                        ->storeFileNamesIn('file_names')
                        ->acceptedFileTypes([
                            'text/plain', 'text/markdown', 'text/csv', 'text/html', 'application/pdf', 'application/json', 'message/rfc822',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'image/png', 'image/jpeg',
                        ])
                        ->maxFiles(20)
                        ->maxSize(30 * 1024)
                        ->helperText('TXT, Markdown, PDF, Word, Excel, CSV, e-maily (EML), snímky obrazovky. Nejvýš 20 souborů po 30 MB.')
                        ->columnSpanFull(),
                    Textarea::make('note')
                        ->label('Poznámka pro agenta')
                        ->placeholder('Zajímají mě hlavně zkušenosti s reklamami. Ceny z roku 2024 už neplatí.')
                        ->rows(2)
                        ->maxLength(2000)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('client')->withCount([
                'entries',
                'entries as proposed_count' => fn ($query) => $query->where('status', KnowledgeEntry::STATUS_PROPOSED),
            ]))
            ->poll(fn (): ?string => KnowledgeSource::query()->whereIn('status', [KnowledgeSource::STATUS_QUEUED, KnowledgeSource::STATUS_RUNNING])->exists() ? '15s' : null)
            ->columns([
                TextColumn::make('title')
                    ->label('Podklady')
                    ->weight('bold')
                    ->description(fn (KnowledgeSource $record): string => collect([$record->client?->name, $record->sizeLabel()])->filter()->implode(' · '))
                    ->wrap()
                    ->searchable(),
                TextColumn::make('entries_count')
                    ->label('Poznatky')
                    ->description(fn (KnowledgeSource $record): ?string => $record->proposed_count > 0 ? $record->proposed_count.' ke schválení' : null),
                TextColumn::make('status')
                    ->label('Stav')
                    ->badge()
                    ->formatStateUsing(fn (KnowledgeSource $record): string => $record->statusLabel())
                    ->color(fn (KnowledgeSource $record): string => $record->statusColor()),
                TextColumn::make('author')->label('Nahrál')->color('gray'),
                TextColumn::make('created_at')->label('Kdy')->since()->sortable()->color('gray'),
            ])
            ->filters([
                SelectFilter::make('client')->label('Klient')->relationship('client', 'name')->searchable()->preload(),
            ])
            ->recordActions([ViewAction::make()->label('Otevřít')])
            ->recordUrl(fn (KnowledgeSource $record): string => self::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Zatím žádné podklady')
            ->emptyStateDescription('Vložte texty nebo soubory se zkušenostmi. Agent z nich udělá krátké poznatky a vy je jen schválíte.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKnowledgeSources::route('/'),
            'create' => CreateKnowledgeSource::route('/nove'),
            'view' => ViewKnowledgeSource::route('/{record}'),
        ];
    }
}
