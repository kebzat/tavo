<?php

namespace App\Filament\Tools\Resources\Demands;

use App\Filament\Tools\Resources\Demands\Pages\CreateDemand;
use App\Filament\Tools\Resources\Demands\Pages\EditDemand;
use App\Filament\Tools\Resources\Demands\Pages\ListDemands;
use App\Filament\Tools\Resources\Demands\Schemas\DemandForm;
use App\Filament\Tools\Resources\Demands\Tables\DemandsTable;
use App\Models\Crm\Demand;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DemandResource extends Resource
{
    protected static ?string $model = Demand::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?string $navigationLabel = 'Poptávky';

    protected static ?string $modelLabel = 'poptávka';

    protected static ?string $pluralModelLabel = 'Poptávky';

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 50;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return DemandForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DemandsTable::configure($table);
    }

    /** Kolik poptávek čeká na reakci. */
    public static function getNavigationBadge(): ?string
    {
        $new = Demand::query()->untouched()->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDemands::route('/'),
            'create' => CreateDemand::route('/create'),
            'edit' => EditDemand::route('/{record}/edit'),
        ];
    }
}
