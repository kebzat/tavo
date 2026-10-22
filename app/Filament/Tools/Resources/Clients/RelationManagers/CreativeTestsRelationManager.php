<?php

namespace App\Filament\Tools\Resources\Clients\RelationManagers;

use App\Enums\Ads\CreativeVerdict;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Paměť kreativních testů. Agent z ní vychází při návrzích textů a
 * kreativ, ať neopakuje, co nefungovalo, bez nového důvodu.
 */
class CreativeTestsRelationManager extends RelationManager
{
    protected static string $relationship = 'creativeTests';

    protected static ?string $title = 'Kreativní testy';

    protected static ?string $modelLabel = 'test';

    protected static ?string $pluralModelLabel = 'testy';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('Test')->required()->maxLength(255)->columnSpanFull()->placeholder('Reels s majitelem vs. produktová fotka'),
            Textarea::make('hypothesis')->label('Co a proč testujeme')->rows(2)->columnSpanFull(),
            TextInput::make('argument')->label('Argument / nabídka'),
            TextInput::make('hook')->label('Hook (úvodní věta, první vteřiny)'),
            TextInput::make('format')->label('Formát')->placeholder('reels, carousel, banner, UGC'),
            TextInput::make('landing_url')->label('Cílová stránka')->url(),
            DatePicker::make('started_on')->label('Od')->native(false)->displayFormat('j. n. Y'),
            DatePicker::make('ended_on')->label('Do')->native(false)->displayFormat('j. n. Y'),
            TextInput::make('budget')->label('Rozpočet')->numeric()->suffix('Kč'),
            Select::make('verdict')->label('Výsledek')->options(CreativeVerdict::class)->default(CreativeVerdict::Running)->required()
                ->helperText('„Málo dat“ není neúspěch: test se nedal vyhodnotit.'),
            Textarea::make('result')->label('Čísla a pozorování')->rows(3)->columnSpanFull(),
            Textarea::make('learning')->label('Co z toho plyne pro další zadání')->rows(2)->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('started_on', 'desc')
            ->columns([
                TextColumn::make('title')->label('Test')->weight('bold')->wrap()
                    ->description(fn ($record): ?string => collect([$record->format, $record->hook])->filter()->implode(' · ') ?: null),
                TextColumn::make('started_on')->label('Od')->date('j. n. Y')->placeholder('–'),
                TextColumn::make('verdict')->label('Výsledek')->badge(),
                TextColumn::make('learning')->label('Poučení')->limit(70)->wrap()->placeholder('–'),
            ])
            ->filters([SelectFilter::make('verdict')->label('Výsledek')->options(CreativeVerdict::class)])
            ->headerActions([CreateAction::make()->label('Zapsat test')])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading('Zatím žádné testy')
            ->emptyStateDescription('Zapisujte, co jste testovali, proč, s jakým rozpočtem a výsledkem. Agent z toho vychází při dalších návrzích.');
    }
}
