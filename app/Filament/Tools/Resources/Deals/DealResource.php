<?php

namespace App\Filament\Tools\Resources\Deals;

use App\Filament\Tools\Resources\Deals\Pages\CreateDeal;
use App\Filament\Tools\Resources\Deals\Pages\EditDeal;
use App\Filament\Tools\Resources\Deals\Pages\ListDeals;
use App\Filament\Tools\Resources\Deals\Schemas\DealForm;
use App\Filament\Tools\Resources\Deals\Tables\DealsTable;
use App\Models\Crm\Deal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DealResource extends Resource
{
    protected static ?string $model = Deal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Obchody';

    protected static ?string $modelLabel = 'obchod';

    protected static ?string $pluralModelLabel = 'Obchody';

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return DealForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DealsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeals::route('/'),
            'create' => CreateDeal::route('/create'),
            'edit' => EditDeal::route('/{record}/edit'),
        ];
    }
}
