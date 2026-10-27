<?php

namespace App\Filament\Tools\Resources\Skills;

use App\Filament\Tools\Pages\Agent\AgentProject;
use App\Filament\Tools\Resources\KnowledgeEntries\KnowledgeEntryResource;
use App\Filament\Tools\Resources\KnowledgeSources\KnowledgeSourceResource;
use App\Filament\Tools\Resources\Skills\Pages\CreateSkill;
use App\Filament\Tools\Resources\Skills\Pages\EditSkill;
use App\Filament\Tools\Resources\Skills\Pages\ListSkills;
use App\Models\KnowledgeEntry;
use App\Models\Skill;
use App\Support\Agent\ClientProjects;
use BackedEnum;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

/**
 * Skilly TAVEO: specialisté, jejich pokyny (Jak pracuje) a kdy je agent
 * použije. Know-how a archiv jsou v mozku (poznatky a podklady se skillem).
 * Viz docs/MOZEK.md, Skilly.
 */
class SkillResource extends Resource
{
    protected static ?string $model = Skill::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $navigationLabel = 'Skilly';

    protected static ?string $modelLabel = 'skill';

    protected static ?string $pluralModelLabel = 'Skilly';

    protected static string|\UnitEnum|null $navigationGroup = 'Mozek';

    protected static ?int $navigationSort = 15;

    protected static ?string $slug = 'mozek/skilly';

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Skill')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Název')
                        ->placeholder('Meta Ads')
                        ->required()
                        ->maxLength(100),
                    TextInput::make('slug')
                        ->label('Zkratka')
                        ->prefix('/')
                        ->placeholder('meta-ads')
                        ->helperText('Agenti ho volají jako /zkratka. Po založení už nejde změnit.')
                        ->required()
                        ->regex('/^[a-z0-9][a-z0-9-]{1,38}$/')
                        ->unique(ignoreRecord: true)
                        ->disabledOn('edit'),
                    Select::make('category')
                        ->label('Skupina')
                        ->options(Skill::categories())
                        ->required(),
                    Select::make('builds_on')
                        ->label('Čerpá ze skillů')
                        ->multiple()
                        ->options(fn (?Skill $record): array => Skill::query()->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))->orderBy('sort')->pluck('name', 'slug')->all())
                        ->helperText('Třeba copywriting: agent si ho načte, když píše texty.'),
                    Textarea::make('description')
                        ->label('Kdy ho použít')
                        ->helperText('Čím se zabývá a kdy ho použít. Podle toho ho agent sám vybere, proto konkrétně: obor, nástroje, typické úkoly.')
                        ->rows(3)
                        ->required()
                        ->maxLength(1000)
                        ->columnSpanFull(),
                    TextInput::make('sort')
                        ->label('Pořadí ve skupině')
                        ->numeric()
                        ->default(100),
                ]),
            Section::make('Jak pracuje')
                ->description('Pokyny pro agenta: co dělá, postup, pravidla, výstup. Know-how (fakta, zkušenosti, čísla) sem nepište, patří do poznatků a archivu skillu, ať se dá schvalovat a dohledat.')
                ->columnSpanFull()
                ->schema([
                    MarkdownEditor::make('instructions')
                        ->hiddenLabel()
                        ->toolbarButtons(['bold', 'italic', 'heading', 'bulletList', 'orderedList', 'link', 'undo', 'redo']),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->modifyQueryUsing(fn ($query) => $query->withCount([
                'entries as approved_count' => fn ($query) => $query->where('status', KnowledgeEntry::STATUS_APPROVED),
                'entries as proposed_count' => fn ($query) => $query->where('status', KnowledgeEntry::STATUS_PROPOSED),
                'sources',
            ]))
            ->groups([
                Group::make('category')
                    ->label('Skupina')
                    ->getTitleFromRecordUsing(fn (Skill $record): string => $record->categoryLabel())
                    ->orderQueryUsing(fn ($query, string $direction) => $query->orderByRaw(self::categoryOrder())),
            ])
            ->defaultGroup('category')
            ->groupingSettingsHidden()
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->label('Skill')
                    ->weight('bold')
                    ->description(fn (Skill $record): string => $record->scope())
                    ->wrap()
                    ->searchable(['name', 'slug', 'description']),
                TextColumn::make('slug')
                    ->label('Volá se')
                    ->formatStateUsing(fn (string $state): string => '/'.$state)
                    ->fontFamily('mono')
                    ->color('gray'),
                TextColumn::make('approved_count')
                    ->label('Know-how')
                    ->formatStateUsing(fn (Skill $record): string => self::count($record->approved_count, 'poznatek', 'poznatky', 'poznatků'))
                    ->description(fn (Skill $record): ?string => $record->proposed_count > 0 ? $record->proposed_count.' ke schválení' : null),
                TextColumn::make('sources_count')
                    ->label('Archiv')
                    ->formatStateUsing(fn (Skill $record): string => self::count($record->sources_count, 'dokument', 'dokumenty', 'dokumentů')),
                TextColumn::make('agent_project')
                    ->label('Agent')
                    ->state(fn (Skill $record): string => self::agentLabel($record))
                    ->color(fn (Skill $record): string => filled($record->agent_project) ? 'gray' : 'warning')
                    ->url(fn (Skill $record): ?string => filled($record->agent_project) ? AgentProject::getUrl(['project' => $record->agent_project]) : null),
            ])
            ->recordUrl(fn (Skill $record): string => self::getUrl('edit', ['record' => $record]))
            ->emptyStateHeading('Zatím žádný skill');
    }

    /** „1 poznatek“, „3 poznatky“, „5 poznatků“ */
    private static function count(int $count, string $one, string $few, string $many): string
    {
        return $count.' '.match (true) {
            $count === 1 => $one,
            $count > 1 && $count < 5 => $few,
            default => $many,
        };
    }

    /** Skupiny v pořadí jako na stránce Projekty (strategie první, provoz poslední). */
    private static function categoryOrder(): string
    {
        $cases = collect(array_keys(Skill::categories()))->map(fn (string $key, int $i): string => "when '{$key}' then {$i}")->implode(' ');

        return "case category {$cases} else 99 end";
    }

    public static function agentLabel(Skill $skill): string
    {
        return match (true) {
            filled($skill->agent_project) => 'projekt '.$skill->agent_project,
            ($skill->agent_project_request['status'] ?? null) === ClientProjects::STATUS_QUEUED => 'zakládá se',
            ($skill->agent_project_request['status'] ?? null) === ClientProjects::STATUS_FAILED => 'nezaložil se: '.($skill->agent_project_request['error'] ?? '?'),
            default => 'bez agenta',
        };
    }

    public static function entriesUrl(Skill $skill): string
    {
        return KnowledgeEntryResource::getUrl('index', ['filters' => ['skill' => ['value' => $skill->getKey()]]]);
    }

    public static function sourcesUrl(Skill $skill): string
    {
        return KnowledgeSourceResource::getUrl('index', ['filters' => ['skill' => ['value' => $skill->getKey()]]]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSkills::route('/'),
            'create' => CreateSkill::route('/novy'),
            'edit' => EditSkill::route('/{record}'),
        ];
    }
}
