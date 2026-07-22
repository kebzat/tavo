<?php

namespace App\Filament\Resources\CaseStudyCategories;

use App\Filament\Resources\CaseStudyCategories\Pages\CreateCaseStudyCategory;
use App\Filament\Resources\CaseStudyCategories\Pages\EditCaseStudyCategory;
use App\Filament\Resources\CaseStudyCategories\Pages\ListCaseStudyCategories;
use App\Filament\Resources\CaseStudyCategories\Schemas\CaseStudyCategoryForm;
use App\Filament\Resources\CaseStudyCategories\Tables\CaseStudyCategoriesTable;
use App\Models\CaseStudyCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CaseStudyCategoryResource extends Resource
{
    protected static ?string $model = CaseStudyCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Kategorie referencí';

    protected static ?string $modelLabel = 'kategorie';

    protected static ?string $pluralModelLabel = 'Kategorie referencí';

    protected static string|\UnitEnum|null $navigationGroup = 'Obsah';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return CaseStudyCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CaseStudyCategoriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCaseStudyCategories::route('/'),
            'create' => CreateCaseStudyCategory::route('/create'),
            'edit' => EditCaseStudyCategory::route('/{record}/edit'),
        ];
    }
}
