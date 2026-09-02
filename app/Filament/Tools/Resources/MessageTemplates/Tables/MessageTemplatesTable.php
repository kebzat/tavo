<?php

namespace App\Filament\Tools\Resources\MessageTemplates\Tables;

use App\Enums\Crm\TemplateChannel;
use App\Models\Crm\MessageTemplate;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class MessageTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('channel')
            ->columns([
                TextColumn::make('name')
                    ->label('Šablona')
                    ->searchable()
                    ->weight('bold')
                    ->wrap()
                    ->description(fn (MessageTemplate $record): string => Str::limit($record->body, 90)),

                TextColumn::make('channel')->label('Kanál')->badge()->color('gray'),

                IconColumn::make('is_active')->label('Aktivní')->boolean(),
            ])
            ->filters([
                SelectFilter::make('channel')->label('Kanál')->options(TemplateChannel::class)->multiple(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->emptyStateHeading('Žádné šablony')
            ->emptyStateDescription('Šablony se seedují při nasazení. Přidej vlastní, když se ustálí formulace, kterou používáš pořád dokola.');
    }
}
