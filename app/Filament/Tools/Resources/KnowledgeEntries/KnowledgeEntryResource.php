<?php

namespace App\Filament\Tools\Resources\KnowledgeEntries;

use App\Filament\Tools\Resources\KnowledgeEntries\Pages\CreateKnowledgeEntry;
use App\Filament\Tools\Resources\KnowledgeEntries\Pages\EditKnowledgeEntry;
use App\Filament\Tools\Resources\KnowledgeEntries\Pages\ListKnowledgeEntries;
use App\Filament\Tools\Resources\KnowledgeSources\KnowledgeSourceResource;
use App\Models\Client;
use App\Models\KnowledgeEntry;
use App\Models\Skill;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Poznatky v mozku TAVEO: návrhy od agentů ke schválení a schválené
 * poznatky, které agenti čtou při práci. Viz docs/MOZEK.md.
 */
class KnowledgeEntryResource extends Resource
{
    protected static ?string $model = KnowledgeEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLightBulb;

    protected static ?string $navigationLabel = 'Poznatky';

    protected static ?string $modelLabel = 'poznatek';

    protected static ?string $pluralModelLabel = 'Poznatky';

    protected static string|\UnitEnum|null $navigationGroup = 'Mozek';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'mozek/poznatky';

    protected static ?string $recordTitleAttribute = 'title';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function getNavigationBadge(): ?string
    {
        $proposed = KnowledgeEntry::query()->proposed()->count();

        return $proposed > 0 ? (string) $proposed : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Návrhy od agentů ke schválení';
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Placeholder::make('replaces_note')
                        ->hiddenLabel()
                        ->visible(fn (?KnowledgeEntry $record): bool => $record?->isProposed() && $record->replaces !== null)
                        ->content(fn (?KnowledgeEntry $record): HtmlString => new HtmlString(
                            '<div class="rounded-lg bg-warning-50 px-4 py-3 text-sm text-warning-800 dark:bg-warning-500/10 dark:text-warning-300">'
                            .'<strong>Nové znění poznatku #'.$record?->replaces_id.'.</strong> Schválením ho nahradí, starý půjde do archivu.<br>'
                            .'<span class="opacity-80">Teď platí: '.e($record?->replaces?->title).' – '.e(Str::limit((string) $record?->replaces?->body, 600)).'</span></div>'
                        ))
                        ->columnSpanFull(),
                    TextInput::make('title')
                        ->label('Nadpis')
                        ->placeholder('Svět Cejlonu nechce v reklamách slevy, jen dárek k objednávce')
                        ->helperText('Jedna věta, která to shrne.')
                        ->required()
                        ->maxLength(200)
                        ->columnSpanFull(),
                    Textarea::make('body')
                        ->label('Poznatek')
                        ->placeholder('Co platí, proč a příklad. U postupu očíslované kroky.')
                        ->rows(8)
                        ->required()
                        ->maxLength(5000)
                        ->columnSpanFull(),
                    Select::make('layer')
                        ->label('Vrstva')
                        ->options(KnowledgeEntry::layers())
                        ->default(KnowledgeEntry::LAYER_COMPANY)
                        ->live()
                        ->required()
                        ->helperText('Firma: platí pro všechny. Klient: jen jeden klient. Postup: návod na opakovanou práci.'),
                    Select::make('client_id')
                        ->label('Klient')
                        ->options(fn (): array => Client::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->visible(fn (Get $get): bool => $get('layer') === KnowledgeEntry::LAYER_CLIENT)
                        ->required(fn (Get $get): bool => $get('layer') === KnowledgeEntry::LAYER_CLIENT),
                    Select::make('skill_id')
                        ->label('Skill')
                        ->options(fn (): array => Skill::query()->orderBy('sort')->pluck('name', 'id')->all())
                        ->placeholder('Žádný')
                        ->helperText('Know-how skillu, nebo zkušenost u klienta v jeho oboru. Skill ji ukáže i agentům ostatních klientů.'),
                    TagsInput::make('tags')
                        ->label('Štítky')
                        ->placeholder('reklamy, reporty, tón…')
                        ->splitKeys([',', 'Enter']),
                    TextInput::make('origin')
                        ->label('Odkud to je')
                        ->placeholder('Pavel, schůzka se Světem Cejlonu 3. 10. 2026')
                        ->maxLength(250),
                    Toggle::make('always')
                        ->label('Vždy v hlavě')
                        ->helperText('Agent ho dostane v každém zadání, i bez hledání. Jen to nejdůležitější, ať se základ vejde (asi 8 000 znaků).')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['client', 'source', 'skill']))
            ->columns([
                TextColumn::make('title')
                    ->label('Poznatek')
                    ->weight('bold')
                    ->grow()
                    ->description(fn (KnowledgeEntry $record): string => Str::limit($record->body, 180))
                    ->wrap()
                    ->searchable(['title', 'body']),
                TextColumn::make('layer')
                    ->label('Vrstva')
                    ->badge()
                    ->color(fn (KnowledgeEntry $record): string => $record->always ? 'warning' : 'gray')
                    ->icon(fn (KnowledgeEntry $record): ?Heroicon => $record->always ? Heroicon::Star : null)
                    ->tooltip(fn (KnowledgeEntry $record): ?string => $record->always ? 'Vždy v hlavě: agent ho dostane v každém zadání' : null)
                    ->formatStateUsing(fn (KnowledgeEntry $record): string => $record->layerLabel())
                    ->description(fn (KnowledgeEntry $record): ?string => collect([$record->client?->name, $record->skill?->name])->filter()->implode(' · ') ?: null),
                TextColumn::make('status')
                    ->label('Stav')
                    ->badge()
                    ->formatStateUsing(fn (KnowledgeEntry $record): string => $record->replaces_id && $record->isProposed() ? 'Nové znění #'.$record->replaces_id : $record->statusLabel())
                    ->color(fn (KnowledgeEntry $record): string => $record->statusColor())
                    ->description(fn (KnowledgeEntry $record): string => KnowledgeEntry::originTypes()[$record->origin_type] ?? $record->origin_type),
                TextColumn::make('origin')
                    ->label('Odkud')
                    ->state(fn (KnowledgeEntry $record): ?string => $record->origin ?? $record->source?->title)
                    ->url(fn (KnowledgeEntry $record): ?string => $record->source ? KnowledgeSourceResource::getUrl('view', ['record' => $record->source]) : null)
                    ->wrap()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->label('Změněno')->since()->sortable()->color('gray'),
            ])
            ->filters([
                SelectFilter::make('layer')->label('Vrstva')->options(KnowledgeEntry::layers()),
                SelectFilter::make('client')->label('Klient')->relationship('client', 'name')->searchable()->preload(),
                SelectFilter::make('skill')->label('Skill')->relationship('skill', 'name'),
                SelectFilter::make('origin_type')->label('Odkud')->options(KnowledgeEntry::originTypes()),
                SelectFilter::make('source')->label('Podklady')->relationship('source', 'title')->searchable(),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Schválit')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (KnowledgeEntry $record): bool => $record->isProposed())
                    ->action(fn (KnowledgeEntry $record) => $record->approve(self::reviewer())),
                Action::make('reject')
                    ->label('Zamítnout')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('gray')
                    ->visible(fn (KnowledgeEntry $record): bool => $record->isProposed())
                    ->iconButton()
                    ->tooltip('Zamítnout')
                    ->action(fn (KnowledgeEntry $record) => $record->reject(self::reviewer())),
                EditAction::make()->iconButton()->tooltip('Upravit'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approveSelected')
                        ->label('Schválit vybrané')
                        ->icon(Heroicon::OutlinedCheck)
                        ->color('success')
                        ->action(function (Collection $records): void {
                            $records->filter(fn (KnowledgeEntry $record): bool => $record->isProposed())->each(fn (KnowledgeEntry $record) => $record->approve(self::reviewer()));
                            Notification::make()->title('Schváleno')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('rejectSelected')
                        ->label('Zamítnout vybrané')
                        ->icon(Heroicon::OutlinedXMark)
                        ->action(fn (Collection $records) => $records->filter(fn (KnowledgeEntry $record): bool => $record->isProposed())->each(fn (KnowledgeEntry $record) => $record->reject(self::reviewer())))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('archiveSelected')
                        ->label('Do archivu')
                        ->icon(Heroicon::OutlinedArchiveBox)
                        ->requiresConfirmation()
                        ->modalDescription('Agenti je přestanou dostávat. Z archivu jdou vrátit.')
                        ->action(fn (Collection $records) => $records->each->update(['status' => KnowledgeEntry::STATUS_ARCHIVED, 'reviewed_by' => self::reviewer(), 'reviewed_at' => now()]))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->emptyStateHeading('Tady zatím nic není')
            ->emptyStateDescription('Poznatky vznikají z nahraných podkladů, z práce agentů (ClickUp, grafika) a z rozhovoru s mozkem. Napsat jde i ručně.');
    }

    public static function reviewer(): string
    {
        return auth()->user()?->name ?? 'nástroje';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKnowledgeEntries::route('/'),
            'create' => CreateKnowledgeEntry::route('/novy'),
            'edit' => EditKnowledgeEntry::route('/{record}'),
        ];
    }
}
