<?php

namespace App\Filament\Tools\Resources\Proposals;

use App\Filament\Tools\Resources\Proposals\Pages\CreateProposal;
use App\Filament\Tools\Resources\Proposals\Pages\EditProposal;
use App\Filament\Tools\Resources\Proposals\Pages\ListProposals;
use App\Filament\Tools\Resources\Proposals\Schemas\ProposalForm;
use App\Filament\Tools\Resources\Proposals\Tables\ProposalsTable;
use App\Models\Proposal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProposalResource extends Resource
{
    protected static ?string $model = Proposal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRocketLaunch;

    protected static ?string $navigationLabel = 'Potenciální spolupráce';

    protected static ?string $modelLabel = 'potenciální spolupráce';

    protected static ?string $pluralModelLabel = 'Potenciální spolupráce';

    protected static ?string $slug = 'potencialni-spoluprace';

    protected static string|\UnitEnum|null $navigationGroup = 'Checklisty';

    protected static ?int $navigationSort = 25;

    protected static ?string $recordTitleAttribute = 'company_name';

    public static function form(Schema $schema): Schema
    {
        return ProposalForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProposalsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProposals::route('/'),
            'create' => CreateProposal::route('/create'),
            'edit' => EditProposal::route('/{record}/edit'),
        ];
    }
}
