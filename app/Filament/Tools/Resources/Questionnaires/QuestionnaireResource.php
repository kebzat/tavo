<?php

namespace App\Filament\Tools\Resources\Questionnaires;

use App\Filament\Tools\Resources\Questionnaires\Pages\CreateQuestionnaire;
use App\Filament\Tools\Resources\Questionnaires\Pages\EditQuestionnaire;
use App\Filament\Tools\Resources\Questionnaires\Pages\ListQuestionnaires;
use App\Models\Questionnaire;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Dotazníky na sdíleném odkazu: pro Pavla, pro klienta před spoluprací.
 * Odpovědi se čtou v okně Odpovědi nebo se stáhnou jako Markdown pro Clauda.
 */
class QuestionnaireResource extends Resource
{
    protected static ?string $model = Questionnaire::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Dotazníky';

    protected static ?string $modelLabel = 'dotazník';

    protected static ?string $pluralModelLabel = 'Dotazníky';

    protected static ?string $slug = 'dotazniky';

    protected static string|\UnitEnum|null $navigationGroup = 'Klienti';

    protected static ?int $navigationSort = 60;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Dotazník')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextInput::make('title')->label('Nadpis')->required()->maxLength(255),
                    TextInput::make('respondent')->label('Kdo odpovídá')->placeholder('Pavel Včeliš')->maxLength(255),
                    Select::make('client_id')
                        ->label('Klient')
                        ->relationship('client', 'name')
                        ->searchable()
                        ->preload()
                        ->helperText('Jen u dotazníku pro klienta. Jeho název se ukáže v hlavičce.'),
                    Toggle::make('is_public')
                        ->label('Sdílet odkaz')
                        ->helperText('Bez zapnutí vidí dotazník jen přihlášený.')
                        ->inline(false),
                    Textarea::make('intro')->label('Úvod')->rows(4)->columnSpanFull(),
                ]),

            Section::make('Otázky')
                ->description('Přejmenovat nebo přesunout otázku jde i potom, co na ni někdo odpověděl. Smazaná otázka vezme odpověď s sebou.')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('sections')
                        ->hiddenLabel()
                        ->schema([
                            TextInput::make('title')->label('Nadpis sekce'),
                            Textarea::make('intro')->label('Úvod sekce')->rows(2),
                            Repeater::make('questions')
                                ->label('Otázky')
                                ->schema([
                                    Hidden::make('key')->default(fn (): string => Str::lower(Str::random(8))),
                                    TextInput::make('label')->label('Otázka')->required()->columnSpanFull(),
                                    Select::make('type')
                                        ->label('Odpověď')
                                        ->options(Questionnaire::TYPES)
                                        ->default('long')
                                        ->required()
                                        ->live(),
                                    TextInput::make('help')->label('Nápověda pod otázkou'),
                                    TagsInput::make('options')
                                        ->label('Možnosti')
                                        ->placeholder('Napište možnost a Enter')
                                        ->reorderable()
                                        ->columnSpanFull()
                                        ->visible(fn (Get $get): bool => in_array($get('type'), ['single', 'multi'], true)),
                                ])
                                ->columns(2)
                                ->defaultItems(1)
                                ->addActionLabel('Přidat otázku')
                                ->collapsible()
                                ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                                ->reorderable(),
                        ])
                        ->defaultItems(1)
                        ->addActionLabel('Přidat sekci')
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                        ->reorderable(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Dotazník')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (Questionnaire $record): string => '/dotaznik/'.$record->slug),
                TextColumn::make('respondent')
                    ->label('Kdo odpovídá')
                    ->description(fn (Questionnaire $record): ?string => $record->client?->name)
                    ->searchable(),
                TextColumn::make('progress')
                    ->label('Zodpovězeno')
                    ->state(fn (Questionnaire $record): string => $record->progress()['answered'].' z '.$record->progress()['total'])
                    ->description(fn (Questionnaire $record): ?string => $record->answered_at?->diffForHumans()),
                TextColumn::make('submitted_at')
                    ->label('Odesláno')
                    ->dateTime('j. n. Y H:i')
                    ->placeholder('zatím ne')
                    ->sortable(),
                IconColumn::make('is_public')->label('Sdíleno')->boolean(),
            ])
            ->recordActions([
                self::answersAction(),
                Action::make('open')
                    ->label(fn (Questionnaire $record): string => $record->is_public ? 'Otevřít' : 'Náhled')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (Questionnaire $record): string => $record->previewUrl(), shouldOpenInNewTab: true),
                EditAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->emptyStateHeading('Žádné dotazníky')
            ->emptyStateDescription('Dotazník se pošle odkazem. Odpovědi se ukládají průběžně a najdete je tady.');
    }

    /** Odpovědi v okně, s tlačítkem na stažení pro Clauda. */
    public static function answersAction(): Action
    {
        return Action::make('answers')
            ->label('Odpovědi')
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('gray')
            ->modalHeading(fn (Questionnaire $record): string => $record->title)
            ->modalDescription(fn (Questionnaire $record): ?string => $record->respondent)
            ->modalContent(fn (Questionnaire $record) => view('filament.tools.questionnaire-answers', [
                'sections' => $record->sectionItems(),
            ]))
            ->modalWidth('4xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Zavřít')
            ->extraModalFooterActions([self::downloadAction()]);
    }

    /** Markdown, který jde rovnou vložit do Claude Code jako podklad. */
    public static function downloadAction(): Action
    {
        return Action::make('download')
            ->label('Stáhnout jako Markdown')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->action(fn (Questionnaire $record): StreamedResponse => response()->streamDownload(
                fn () => print ($record->toMarkdown()),
                'dotaznik-'.$record->slug.'.md',
                ['Content-Type' => 'text/markdown; charset=UTF-8'],
            ));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQuestionnaires::route('/'),
            'create' => CreateQuestionnaire::route('/create'),
            'edit' => EditQuestionnaire::route('/{record}/edit'),
        ];
    }
}
