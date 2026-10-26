<?php

namespace App\Filament\Tools\Resources\Clients\RelationManagers;

use App\Filament\Tools\Resources\KnowledgeSources\KnowledgeSourceResource;
use App\Models\KnowledgeSource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Archiv klienta: data, strategie, výsledky, marže. Jsou to podklady mozku
 * s tímhle klientem, agent v projektu klienta je vidí v každé konverzaci
 * a přečte celé. Nahrává se stejným formulářem jako do mozku. Viz docs/MOZEK.md.
 */
class ArchiveRelationManager extends RelationManager
{
    protected static string $relationship = 'knowledgeSources';

    protected static ?string $title = 'Archiv';

    protected static ?string $modelLabel = 'dokument';

    protected static ?string $pluralModelLabel = 'dokumenty';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Dokument')
                    ->weight('bold')
                    ->description(fn (KnowledgeSource $record): ?string => $record->summary ? str($record->summary)->limit(160)->toString() : $record->sizeLabel())
                    ->wrap()
                    ->searchable(),
                TextColumn::make('category')
                    ->label('Druh')
                    ->visibleFrom('sm')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (KnowledgeSource $record): ?string => $record->categoryLabel())
                    ->placeholder('–'),
                TextColumn::make('document_date')->label('K datu')->date('j. n. Y')->placeholder('–')->visibleFrom('md'),
                TextColumn::make('status')
                    ->label('Mozek')
                    ->visibleFrom('md')
                    ->badge()
                    ->formatStateUsing(fn (KnowledgeSource $record): string => $record->statusLabel())
                    ->color(fn (KnowledgeSource $record): string => $record->statusColor()),
                TextColumn::make('created_at')->label('Nahráno')->since()->sortable()->color('gray')->visibleFrom('lg')
                    ->description(fn (KnowledgeSource $record): ?string => $record->author),
            ])
            ->filters([
                SelectFilter::make('category')->label('Druh')->options(KnowledgeSource::categories()),
            ])
            ->headerActions([
                Action::make('upload')
                    ->label('Nahrát do archivu')
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->url(fn (): string => KnowledgeSourceResource::getUrl('create', ['klient' => $this->getOwnerRecord()->getKey()])),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Otevřít')
                    ->url(fn (KnowledgeSource $record): string => KnowledgeSourceResource::getUrl('view', ['record' => $record])),
                KnowledgeSourceResource::deleteAction(DeleteAction::make())->iconButton(),
            ])
            ->recordUrl(fn (KnowledgeSource $record): string => KnowledgeSourceResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Archiv je prázdný')
            ->emptyStateDescription('Nahrajte dokumenty klienta: data, vizi a strategii, výsledky, obchodní data a marže. Agent v projektu klienta je uvidí v každé konverzaci a z nových navrhne poznatky do mozku.');
    }
}
