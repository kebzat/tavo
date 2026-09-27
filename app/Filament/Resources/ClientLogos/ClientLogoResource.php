<?php

namespace App\Filament\Resources\ClientLogos;

use App\Filament\Resources\ClientLogos\Pages\CreateClientLogo;
use App\Filament\Resources\ClientLogos\Pages\EditClientLogo;
use App\Filament\Resources\ClientLogos\Pages\ListClientLogos;
use App\Filament\Resources\ClientLogos\Schemas\ClientLogoForm;
use App\Filament\Resources\ClientLogos\Tables\ClientLogosTable;
use App\Models\ClientLogo;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ClientLogoResource extends Resource
{
    protected static ?string $model = ClientLogo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $navigationLabel = 'Loga klientů';

    protected static ?string $modelLabel = 'logo';

    protected static ?string $pluralModelLabel = 'Loga klientů';

    protected static string|\UnitEnum|null $navigationGroup = 'Obsah';

    protected static ?int $navigationSort = 45;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ClientLogoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClientLogosTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClientLogos::route('/'),
            'create' => CreateClientLogo::route('/create'),
            'edit' => EditClientLogo::route('/{record}/edit'),
        ];
    }
}
