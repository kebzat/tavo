<?php

namespace App\Filament\Tools\Resources\Clients\RelationManagers;

use App\Models\Marketing\LeadFeedback;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Kvalita leadů od klienta: co se s leady z reklam stalo. Bez toho agent
 * ani report levný lead za úspěch nevydávají. Viz pravidlo LeadFeedbackMissing.
 */
class LeadFeedbackRelationManager extends RelationManager
{
    protected static string $relationship = 'leadFeedback';

    protected static ?string $title = 'Kvalita leadů';

    protected static ?string $modelLabel = 'zpětná vazba';

    protected static ?string $pluralModelLabel = 'zpětná vazba';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('period_from')->label('Od')->native(false)->displayFormat('j. n. Y')->required()->default(now()->subMonth()->startOfMonth()),
            DatePicker::make('period_to')->label('Do')->native(false)->displayFormat('j. n. Y')->required()->afterOrEqual('period_from')->default(now()->subMonth()->endOfMonth()),
            TextInput::make('leads')->label('Leadů u klienta')->numeric()->minValue(0),
            TextInput::make('contacted')->label('Ozvali se / dovolali se')->numeric()->minValue(0),
            TextInput::make('qualified')->label('Relevantní')->numeric()->minValue(0),
            TextInput::make('deals')->label('Obchody')->numeric()->minValue(0),
            TextInput::make('deal_value')->label('Hodnota obchodů')->numeric()->minValue(0)->suffix('Kč'),
            TextInput::make('source')->label('Odkud čísla jsou')->placeholder('CRM klienta, tabulka, telefonát'),
            Textarea::make('note')->label('Poznámka')->rows(2)->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('period_to', 'desc')
            ->columns([
                TextColumn::make('period_from')->label('Období')->formatStateUsing(fn (LeadFeedback $record): string => $record->period_from->format('j. n.').' – '.$record->period_to->format('j. n. Y'))->weight('bold'),
                TextColumn::make('leads')->label('Leady')->placeholder('–'),
                TextColumn::make('contacted')->label('Ozvali se')->placeholder('–'),
                TextColumn::make('qualified')->label('Relevantní')->placeholder('–'),
                TextColumn::make('deals')->label('Obchody')->placeholder('–'),
                TextColumn::make('deal_value')->label('Hodnota')->money('CZK', locale: 'cs')->placeholder('–'),
                TextColumn::make('source')->label('Zdroj')->placeholder('–'),
            ])
            ->headerActions([CreateAction::make()->label('Zapsat zpětnou vazbu')])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->emptyStateHeading('Bez zpětné vazby')
            ->emptyStateDescription('U leadových kampaní si od klienta pravidelně vyžádejte, kolik leadů se ozvalo, kolik bylo relevantních a kolik skončilo obchodem.');
    }
}
