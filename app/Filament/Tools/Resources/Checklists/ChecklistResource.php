<?php

namespace App\Filament\Tools\Resources\Checklists;

use App\Filament\Tools\Resources\Checklists\Pages\CreateChecklist;
use App\Filament\Tools\Resources\Checklists\Pages\EditChecklist;
use App\Filament\Tools\Resources\Checklists\Pages\ListChecklists;
use App\Filament\Tools\Resources\Checklists\RelationManagers\CategoriesRelationManager;
use App\Filament\Tools\Resources\Checklists\RelationManagers\ItemsRelationManager;
use App\Filament\Tools\Resources\Checklists\RelationManagers\SectionsRelationManager;
use App\Filament\Tools\Resources\Checklists\Schemas\ChecklistForm;
use App\Filament\Tools\Resources\Checklists\Tables\ChecklistsTable;
use App\Models\Checklist;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ChecklistResource extends Resource
{
    protected static ?string $model = Checklist::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Checklisty';

    protected static ?string $modelLabel = 'checklist';

    protected static ?string $pluralModelLabel = 'Checklisty';

    protected static string|\UnitEnum|null $navigationGroup = 'Checklisty';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ChecklistForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ChecklistsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
            CategoriesRelationManager::class,
            SectionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChecklists::route('/'),
            'create' => CreateChecklist::route('/create'),
            'edit' => EditChecklist::route('/{record}/edit'),
        ];
    }
}
