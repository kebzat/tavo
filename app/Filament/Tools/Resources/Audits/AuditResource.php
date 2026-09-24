<?php

namespace App\Filament\Tools\Resources\Audits;

use App\Filament\Tools\Resources\Audits\Pages\CreateAudit;
use App\Filament\Tools\Resources\Audits\Pages\EditAudit;
use App\Filament\Tools\Resources\Audits\Pages\ListAudits;
use App\Filament\Tools\Resources\Audits\Schemas\AuditForm;
use App\Filament\Tools\Resources\Audits\Tables\AuditsTable;
use App\Models\Audit;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AuditResource extends Resource
{
    protected static ?string $model = Audit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentMagnifyingGlass;

    protected static ?string $navigationLabel = 'Audity';

    protected static ?string $modelLabel = 'audit';

    protected static ?string $pluralModelLabel = 'Audity';

    protected static string|\UnitEnum|null $navigationGroup = 'Checklisty';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return AuditForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AuditsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAudits::route('/'),
            'create' => CreateAudit::route('/create'),
            'edit' => EditAudit::route('/{record}/edit'),
        ];
    }
}
