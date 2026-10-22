<?php

namespace App\Filament\Tools\Resources\Clients\RelationManagers;

use App\Models\Marketing\ClientBriefing;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Ranní přehledy od agenta, historie po dnech. */
class BriefingsRelationManager extends RelationManager
{
    protected static string $relationship = 'briefings';

    protected static ?string $title = 'Ranní přehledy';

    protected static ?string $modelLabel = 'přehled';

    protected static ?string $pluralModelLabel = 'přehledy';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')->label('Den')->date('j. n. Y')->weight('bold'),
                TextColumn::make('body')->label('Přehled')->limit(140)->wrap(),
            ])
            ->recordActions([
                Action::make('show')
                    ->label('Číst')
                    ->modalHeading(fn (ClientBriefing $record): string => 'Ranní přehled '.$record->date->format('j. n. Y'))
                    ->modalContent(fn (ClientBriefing $record) => view('filament.tools.partials.briefing', ['briefing' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Zavřít'),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Zatím žádný přehled')
            ->emptyStateDescription('Agent píše ranní přehled ve všední dny v 6:50, když má klient reklamy nebo vyplněnou kartu.');
    }
}
