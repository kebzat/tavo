<?php

namespace App\Filament\Tools\Resources\Clients\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

/** Cíl měsíce a komentář „Co jsme zjistili“ na přehledu pro klienta. */
class MonthsRelationManager extends RelationManager
{
    protected static string $relationship = 'months';

    protected static ?string $title = 'Měsíce';

    protected static ?string $modelLabel = 'měsíc';

    protected static ?string $pluralModelLabel = 'měsíce';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('month')
                ->label('Měsíc')
                ->native(false)
                ->displayFormat('F Y')
                ->default(now()->startOfMonth())
                ->required()
                ->dehydrateStateUsing(fn ($state) => $state ? now()->parse($state)->startOfMonth()->toDateString() : null)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('client_id', $this->getOwnerRecord()->getKey())),
            TextInput::make('goal')
                ->label('Cíl měsíce')
                ->maxLength(255)
                ->placeholder('Zrychlit mobilní web a opravit košík'),
            MarkdownEditor::make('summary')
                ->label('Co jsme zjistili')
                ->helperText('Rozhodnutí a zjištění za měsíc, i co jsme doporučili neřešit a proč. Klient to vidí na přehledu.')
                ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList']),
        ])->columns(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('month', 'desc')
            ->columns([
                TextColumn::make('month')->label('Měsíc')->date('F Y')->weight('bold'),
                TextColumn::make('goal')->label('Cíl')->wrap()->placeholder('–'),
                TextColumn::make('summary')->label('Co jsme zjistili')->limit(80)->placeholder('–'),
            ])
            ->headerActions([CreateAction::make()->label('Přidat měsíc')])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading('Bez cílů a komentářů')
            ->emptyStateDescription('Cíl měsíce se ukáže v hlavičce přehledu, komentář v sekci Co jsme zjistili.');
    }
}
