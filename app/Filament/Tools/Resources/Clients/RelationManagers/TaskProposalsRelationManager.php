<?php

namespace App\Filament\Tools\Resources\Clients\RelationManagers;

use App\Models\ClientTask;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

/**
 * Návrhy úkolů od agenta (ze schůzek, e-mailů, zpráv). Klient je nevidí.
 * Schválením se z návrhu stane běžný úkol. Termíny a odpovědnost agent
 * nedomýšlí: co ve zdroji nebylo, zůstává prázdné k doplnění.
 */
class TaskProposalsRelationManager extends RelationManager
{
    protected static string $relationship = 'taskProposals';

    protected static ?string $title = 'Návrhy úkolů';

    protected static ?string $modelLabel = 'návrh úkolu';

    protected static ?string $pluralModelLabel = 'návrhy úkolů';

    public function form(Schema $schema): Schema
    {
        return $schema->components(TasksRelationManager::fields($this->getOwnerRecord()));
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Návrh')->weight('bold')->wrap()
                    ->description(fn (ClientTask $record): ?string => $record->source ? 'Zdroj: '.$record->source : null),
                TextColumn::make('priority')->label('Priorita')
                    ->formatStateUsing(fn (?string $state): string => ['high' => 'vysoká', 'normal' => 'běžná', 'low' => 'nízká'][$state] ?? '–')
                    ->placeholder('–'),
                TextColumn::make('due_on')->label('Termín')->date('j. n. Y')->placeholder('k doplnění'),
                TextColumn::make('waiting_for')->label('Čeká se na')->placeholder('–')->wrap(),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Schválit')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->action(function (ClientTask $record): void {
                        $record->update(['is_proposal' => false]);
                        Notification::make()->success()->title('Úkol založen')->send();
                    }),
                EditAction::make(),
                DeleteAction::make()->label('Zahodit'),
            ])
            ->toolbarActions([
                BulkAction::make('approveSelected')
                    ->label('Schválit vybrané')
                    ->icon(Heroicon::OutlinedCheck)
                    ->action(fn (Collection $records) => $records->each->update(['is_proposal' => false]))
                    ->deselectRecordsAfterCompletion(),
                DeleteBulkAction::make()->label('Zahodit vybrané'),
            ])
            ->emptyStateHeading('Žádné návrhy')
            ->emptyStateDescription('Dejte agentovi zápis ze schůzky nebo e-mail a požádejte ho o návrh úkolů. Objeví se tady ke schválení.');
    }
}
