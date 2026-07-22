<?php

namespace App\Filament\Resources\ProcessSteps;

use App\Filament\Resources\ProcessSteps\Pages\CreateProcessStep;
use App\Filament\Resources\ProcessSteps\Pages\EditProcessStep;
use App\Filament\Resources\ProcessSteps\Pages\ListProcessSteps;
use App\Filament\Resources\ProcessSteps\Schemas\ProcessStepForm;
use App\Filament\Resources\ProcessSteps\Tables\ProcessStepsTable;
use App\Models\ProcessStep;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProcessStepResource extends Resource
{
    protected static ?string $model = ProcessStep::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static ?string $navigationLabel = 'Postup spolupráce';

    protected static ?string $modelLabel = 'krok';

    protected static ?string $pluralModelLabel = 'Kroky postupu';

    protected static string|\UnitEnum|null $navigationGroup = 'Obsah';

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return ProcessStepForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProcessStepsTable::configure($table);
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
            'index' => ListProcessSteps::route('/'),
            'create' => CreateProcessStep::route('/create'),
            'edit' => EditProcessStep::route('/{record}/edit'),
        ];
    }
}
