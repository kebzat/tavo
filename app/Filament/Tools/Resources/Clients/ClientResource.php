<?php

namespace App\Filament\Tools\Resources\Clients;

use App\Filament\Tools\Resources\Clients\Pages\CreateClient;
use App\Filament\Tools\Resources\Clients\Pages\EditClient;
use App\Filament\Tools\Resources\Clients\Pages\ListClients;
use App\Filament\Tools\Resources\Clients\RelationManagers\AdAccountsRelationManager;
use App\Filament\Tools\Resources\Clients\RelationManagers\ArchiveRelationManager;
use App\Filament\Tools\Resources\Clients\RelationManagers\BriefingsRelationManager;
use App\Filament\Tools\Resources\Clients\RelationManagers\CreativeTestsRelationManager;
use App\Filament\Tools\Resources\Clients\RelationManagers\KnowledgeRelationManager;
use App\Filament\Tools\Resources\Clients\RelationManagers\LeadFeedbackRelationManager;
use App\Filament\Tools\Resources\Clients\RelationManagers\MonthsRelationManager;
use App\Filament\Tools\Resources\Clients\RelationManagers\TaskProposalsRelationManager;
use App\Filament\Tools\Resources\Clients\RelationManagers\TasksRelationManager;
use App\Filament\Tools\Resources\Clients\Schemas\ClientForm;
use App\Filament\Tools\Resources\Clients\Tables\ClientsTable;
use App\Models\Client;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Klienti';

    protected static ?string $modelLabel = 'klient';

    protected static ?string $pluralModelLabel = 'Klienti';

    protected static string|\UnitEnum|null $navigationGroup = 'Klienti';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ClientForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClientsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            TasksRelationManager::class,
            TaskProposalsRelationManager::class,
            BriefingsRelationManager::class,
            MonthsRelationManager::class,
            AdAccountsRelationManager::class,
            LeadFeedbackRelationManager::class,
            CreativeTestsRelationManager::class,
            ArchiveRelationManager::class,
            KnowledgeRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClients::route('/'),
            'create' => CreateClient::route('/create'),
            'edit' => EditClient::route('/{record}/edit'),
        ];
    }
}
