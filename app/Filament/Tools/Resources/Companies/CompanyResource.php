<?php

namespace App\Filament\Tools\Resources\Companies;

use App\Filament\Tools\Resources\Companies\Pages\CreateCompany;
use App\Filament\Tools\Resources\Companies\Pages\EditCompany;
use App\Filament\Tools\Resources\Companies\Pages\ListCompanies;
use App\Filament\Tools\Resources\Companies\RelationManagers\ActivitiesRelationManager;
use App\Filament\Tools\Resources\Companies\RelationManagers\ContactsRelationManager;
use App\Filament\Tools\Resources\Companies\RelationManagers\DealsRelationManager;
use App\Filament\Tools\Resources\Companies\Schemas\CompanyForm;
use App\Filament\Tools\Resources\Companies\Tables\CompaniesTable;
use App\Models\Crm\Company;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $navigationLabel = 'Firmy';

    protected static ?string $modelLabel = 'firma';

    protected static ?string $pluralModelLabel = 'Firmy';

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return CompanyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CompaniesTable::configure($table);
    }

    /** Kontakty, obchody a časová osa na jedné obrazovce s kartou firmy. */
    public static function getRelations(): array
    {
        return [
            ContactsRelationManager::class,
            DealsRelationManager::class,
            ActivitiesRelationManager::class,
        ];
    }

    /** Počet firem s propásnutým follow-upem. V navigaci funguje jako budík. */
    public static function getNavigationBadge(): ?string
    {
        $overdue = Company::query()->overdue()->count();

        return $overdue > 0 ? (string) $overdue : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Firmy s propásnutým follow-upem';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanies::route('/'),
            'create' => CreateCompany::route('/create'),
            'edit' => EditCompany::route('/{record}/edit'),
        ];
    }
}
