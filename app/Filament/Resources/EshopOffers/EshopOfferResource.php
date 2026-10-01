<?php

namespace App\Filament\Resources\EshopOffers;

use App\Filament\Resources\EshopOffers\Pages\CreateEshopOffer;
use App\Filament\Resources\EshopOffers\Pages\EditEshopOffer;
use App\Filament\Resources\EshopOffers\Pages\ListEshopOffers;
use App\Filament\Resources\EshopOffers\Schemas\EshopOfferForm;
use App\Filament\Resources\EshopOffers\Tables\EshopOffersTable;
use App\Models\EshopOffer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EshopOfferResource extends Resource
{
    protected static ?string $model = EshopOffer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?string $navigationLabel = 'Nabídky pro e-shopy';

    protected static ?string $modelLabel = 'nabídka pro e-shopy';

    protected static ?string $pluralModelLabel = 'Nabídky pro e-shopy';

    // Jinak by z nadpisu bylo „Nabídky Pro E-shopy".
    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|\UnitEnum|null $navigationGroup = 'Obsah';

    protected static ?int $navigationSort = 25;

    protected static ?string $recordTitleAttribute = 'nav_label';

    public static function form(Schema $schema): Schema
    {
        return EshopOfferForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EshopOffersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEshopOffers::route('/'),
            'create' => CreateEshopOffer::route('/create'),
            'edit' => EditEshopOffer::route('/{record}/edit'),
        ];
    }
}
