<?php

namespace App\Filament\Resources\WebTexts;

use App\Filament\Resources\WebTexts\Pages\EditWebText;
use App\Filament\Resources\WebTexts\Pages\ListWebTexts;
use App\Filament\Resources\WebTexts\Schemas\WebTextForm;
use App\Filament\Resources\WebTexts\Tables\WebTextsTable;
use App\Models\WebText;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Texty, které nemají vlastní pole v nastavení: nadpisy a perexy výpisů,
 * popisky tlačítek, hlášky formuláře.
 *
 * Nezakládají se ručně. Vznikají samy, jakmile web vykreslí šablonu s klíčem,
 * viz App\Support\WebTexts. Ruční zakládání by dělalo klíče, které nikde nejsou.
 */
class WebTextResource extends Resource
{
    protected static ?string $model = WebText::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static ?string $navigationLabel = 'Statické texty';

    protected static ?string $modelLabel = 'text';

    protected static ?string $pluralModelLabel = 'Statické texty';

    protected static string|\UnitEnum|null $navigationGroup = 'Nastavení';

    protected static ?int $navigationSort = 80;

    protected static ?string $recordTitleAttribute = 'key';

    public static function form(Schema $schema): Schema
    {
        return WebTextForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WebTextsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWebTexts::route('/'),
            'edit' => EditWebText::route('/{record}/edit'),
        ];
    }
}
