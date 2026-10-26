<?php

namespace App\Filament\Tools\Resources\KnowledgeEntries\Pages;

use App\Filament\Tools\Resources\KnowledgeEntries\KnowledgeEntryResource;
use App\Filament\Tools\Resources\KnowledgeSources\KnowledgeSourceResource;
use App\Models\KnowledgeEntry;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;

class ListKnowledgeEntries extends ListRecords
{
    protected static string $resource = KnowledgeEntryResource::class;

    public function getSubheading(): ?string
    {
        return 'Společná paměť: co agenti dostanou do zadání a co najdou, když hledají. Návrhy od agentů začnou platit, až je tady schválíte.';
    }

    /** Bez návrhů ke schválení rovnou to, co platí. */
    public function getDefaultActiveTab(): string|int|null
    {
        return KnowledgeEntry::query()->proposed()->exists() ? 'navrhy' : 'plati';
    }

    public function getTabs(): array
    {
        return [
            'navrhy' => Tab::make('Ke schválení')
                ->badge(fn (): ?int => KnowledgeEntry::query()->proposed()->count() ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn ($query) => $query->proposed()),
            'plati' => Tab::make('Platí')
                ->modifyQueryUsing(fn ($query) => $query->approved()),
            'vzdy' => Tab::make('Vždy v hlavě')
                ->modifyQueryUsing(fn ($query) => $query->approved()->where('always', true)),
            'archiv' => Tab::make('Zamítnuté a archiv')
                ->modifyQueryUsing(fn ($query) => $query->whereIn('status', [KnowledgeEntry::STATUS_REJECTED, KnowledgeEntry::STATUS_ARCHIVED])),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sources')
                ->label('Nahrát podklady')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->url(KnowledgeSourceResource::getUrl('create')),
            CreateAction::make()->label('Napsat poznatek'),
        ];
    }
}
