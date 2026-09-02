<?php

namespace App\Filament\Tools\Resources\Tags;

use App\Filament\Tools\Resources\Tags\Pages\ListTags;
use App\Models\Crm\Tag;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Štítky se běžně zakládají rovnou u firmy. Tenhle seznam slouží k úklidu —
 * přejmenovat překlep, smazat štítek, který se neujal.
 */
class TagResource extends Resource
{
    protected static ?string $model = Tag::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Štítky';

    protected static ?string $modelLabel = 'štítek';

    protected static ?string $pluralModelLabel = 'Štítky';

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 80;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Název')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Štítek')->searchable()->weight('bold'),
                TextColumn::make('companies_count')->label('Firem')->counts('companies')->sortable(),
            ])
            ->recordActions([EditAction::make()->label('')->iconButton(), DeleteAction::make()->label('')->iconButton()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->emptyStateHeading('Žádné štítky')
            ->emptyStateDescription('Štítek se zakládá rovnou u firmy — tady se pak dá přejmenovat nebo smazat.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTags::route('/'),
        ];
    }
}
