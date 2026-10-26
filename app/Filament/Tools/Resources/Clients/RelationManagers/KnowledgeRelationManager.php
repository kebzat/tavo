<?php

namespace App\Filament\Tools\Resources\Clients\RelationManagers;

use App\Filament\Tools\Resources\KnowledgeEntries\KnowledgeEntryResource;
use App\Models\KnowledgeEntry;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Mozek klienta: poznatky o tomhle klientovi (platné a ke schválení), které
 * agent dostává v každé konverzaci v projektu klienta. Úpravy a hromadné
 * schvalování jsou v Mozku → Poznatky. Viz docs/MOZEK.md.
 */
class KnowledgeRelationManager extends RelationManager
{
    protected static string $relationship = 'knowledgeEntries';

    protected static ?string $title = 'Mozek klienta';

    protected static ?string $modelLabel = 'poznatek';

    protected static ?string $pluralModelLabel = 'poznatky';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->whereIn('status', [KnowledgeEntry::STATUS_PROPOSED, KnowledgeEntry::STATUS_APPROVED])
                ->orderByRaw('status = ? desc', [KnowledgeEntry::STATUS_PROPOSED])
                ->latest('updated_at'))
            ->columns([
                TextColumn::make('title')
                    ->label('Poznatek')
                    ->weight('bold')
                    ->description(fn (KnowledgeEntry $record): string => str($record->body)->limit(220)->toString())
                    ->wrap()
                    ->searchable(['title', 'body']),
                TextColumn::make('status')
                    ->label('Stav')
                    ->badge()
                    ->formatStateUsing(fn (KnowledgeEntry $record): string => $record->statusLabel())
                    ->color(fn (KnowledgeEntry $record): string => $record->statusColor()),
                TextColumn::make('origin_type')
                    ->label('Odkud')
                    ->formatStateUsing(fn (?string $state): string => KnowledgeEntry::originTypes()[$state] ?? (string) $state)
                    ->color('gray'),
                TextColumn::make('updated_at')->label('Změněno')->since()->color('gray'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Stav')->options([
                    KnowledgeEntry::STATUS_PROPOSED => 'Ke schválení',
                    KnowledgeEntry::STATUS_APPROVED => 'Platí',
                ]),
            ])
            ->headerActions([
                Action::make('all')
                    ->label('Otevřít v mozku')
                    ->icon(Heroicon::OutlinedLightBulb)
                    ->color('gray')
                    ->url(fn (): string => KnowledgeEntryResource::getUrl('index', ['filters' => ['client' => ['value' => $this->getOwnerRecord()->getKey()]]])),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Schválit')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (KnowledgeEntry $record): bool => $record->isProposed())
                    ->action(fn (KnowledgeEntry $record) => $record->approve(KnowledgeEntryResource::reviewer())),
                Action::make('reject')
                    ->label('Zamítnout')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('gray')
                    ->visible(fn (KnowledgeEntry $record): bool => $record->isProposed())
                    ->action(fn (KnowledgeEntry $record) => $record->reject(KnowledgeEntryResource::reviewer())),
                Action::make('edit')
                    ->label('Upravit')
                    ->iconButton()
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (KnowledgeEntry $record): string => KnowledgeEntryResource::getUrl('edit', ['record' => $record])),
            ])
            ->emptyStateHeading('Mozek o klientovi zatím nic neví')
            ->emptyStateDescription('Poznatky přibudou z archivu, z úloh v ClickUpu a z konverzací s agentem v projektu klienta. Platí, až je schválíte.');
    }
}
