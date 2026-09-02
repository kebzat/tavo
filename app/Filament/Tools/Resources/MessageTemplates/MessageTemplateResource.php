<?php

namespace App\Filament\Tools\Resources\MessageTemplates;

use App\Filament\Tools\Resources\MessageTemplates\Pages\CreateMessageTemplate;
use App\Filament\Tools\Resources\MessageTemplates\Pages\EditMessageTemplate;
use App\Filament\Tools\Resources\MessageTemplates\Pages\ListMessageTemplates;
use App\Filament\Tools\Resources\MessageTemplates\Schemas\MessageTemplateForm;
use App\Filament\Tools\Resources\MessageTemplates\Tables\MessageTemplatesTable;
use App\Models\Crm\MessageTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MessageTemplateResource extends Resource
{
    protected static ?string $model = MessageTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static ?string $navigationLabel = 'Šablony zpráv';

    protected static ?string $modelLabel = 'šablona';

    protected static ?string $pluralModelLabel = 'Šablony zpráv';

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 70;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return MessageTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MessageTemplatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMessageTemplates::route('/'),
            'create' => CreateMessageTemplate::route('/create'),
            'edit' => EditMessageTemplate::route('/{record}/edit'),
        ];
    }
}
