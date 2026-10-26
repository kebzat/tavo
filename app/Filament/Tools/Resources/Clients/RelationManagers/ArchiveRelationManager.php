<?php

namespace App\Filament\Tools\Resources\Clients\RelationManagers;

use App\Filament\Tools\Resources\KnowledgeSources\KnowledgeSourceResource;
use App\Models\KnowledgeSource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Js;
use Illuminate\Support\Str;

/**
 * Archiv klienta: data, strategie, výsledky, marže. Jsou to podklady mozku
 * s tímhle klientem, agent v projektu klienta je vidí v každé konverzaci
 * a přečte celé. Nahrává se stejným formulářem jako do mozku. Viz docs/MOZEK.md.
 *
 * Řádek je seznam dokumentů, ne tabulka čísel: název s popisem přes celou šířku,
 * vpravo druh. Kliknutím se otevře detail, stránku (HTML od agenta) jedním tlačítkem.
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
                Split::make([
                    Stack::make([
                        TextColumn::make('title')
                            ->weight(FontWeight::SemiBold)
                            ->searchable(),
                        // Popis od knihovníka, u vloženého textu jeho začátek.
                        TextColumn::make('summary')
                            ->state(fn (KnowledgeSource $record): string => $record->summary ?: (Str::limit(Str::squish((string) $record->body), 200) ?: $record->sizeLabel()))
                            ->color('gray')
                            ->size(TextSize::Small)
                            ->lineClamp(2),
                        TextColumn::make('created_at')
                            ->color('gray')
                            ->size(TextSize::ExtraSmall)
                            ->formatStateUsing(fn (KnowledgeSource $record): string => collect([
                                $record->document_date ? 'k '.$record->document_date->format('j. n. Y') : null,
                                'nahrál '.($record->author ?: '?').' '.$record->created_at->format('j. n. Y'),
                                $record->isStored() ? null : 'mozek: '.mb_strtolower($record->statusLabel()),
                            ])->filter()->implode(' · ')),
                    ])->space(1),
                    TextColumn::make('category')
                        ->badge()
                        ->color('gray')
                        ->formatStateUsing(fn (KnowledgeSource $record): ?string => $record->categoryLabel())
                        ->grow(false)
                        ->visibleFrom('md'),
                ])->from('sm'),
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
                // Rozbor nebo plán od agenta (HTML) jako stálá stránka, odkaz jde poslat dál.
                Action::make('page')
                    ->label('Stránka')
                    ->icon(Heroicon::OutlinedGlobeAlt)
                    ->button()
                    ->size('sm')
                    ->color('gray')
                    ->visible(fn (KnowledgeSource $record): bool => $record->pageFile() !== null)
                    ->url(fn (KnowledgeSource $record): ?string => $record->pageUrl(), shouldOpenInNewTab: true),
                ActionGroup::make([
                    Action::make('copyPage')
                        ->label('Zkopírovat odkaz')
                        ->icon(Heroicon::OutlinedLink)
                        ->visible(fn (KnowledgeSource $record): bool => $record->pageFile() !== null)
                        ->alpineClickHandler(fn (KnowledgeSource $record): string => 'navigator.clipboard.writeText('.Js::from($record->pageUrl()).').then(() => new FilamentNotification().title('.Js::from('Odkaz zkopírován').').success().send())'),
                    Action::make('open')
                        ->label('Detail dokumentu')
                        ->icon(Heroicon::OutlinedDocumentText)
                        ->url(fn (KnowledgeSource $record): string => KnowledgeSourceResource::getUrl('view', ['record' => $record])),
                    KnowledgeSourceResource::deleteAction(DeleteAction::make())->label('Smazat'),
                ])->color('gray'),
            ])
            ->recordUrl(fn (KnowledgeSource $record): string => KnowledgeSourceResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Archiv je prázdný')
            ->emptyStateDescription('Nahrajte dokumenty klienta: data, vizi a strategii, výsledky, obchodní data a marže. Agent v projektu klienta je uvidí v každé konverzaci a z nových navrhne poznatky do mozku.');
    }
}
