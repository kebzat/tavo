<?php

namespace App\Filament\Tools\Resources\AdAlerts\Pages;

use App\Filament\Tools\Resources\AdAlerts\AdAlertResource;
use App\Models\Ads\AdAlert;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListAdAlerts extends ListRecords
{
    protected static string $resource = AdAlertResource::class;

    /** Záložka z ranního souhrnu (?kategorie=data / performance). */
    #[Url(as: 'kategorie')]
    public ?string $activeTab = null;

    public function getSubheading(): ?string
    {
        return 'Problémy s daty (stahování, měření, stránky) jsou oddělené od výkonu proti cíli. Odložené se ukážou zase v den, do kdy jste je odložili.';
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Všechna'),
            AdAlert::DATA => Tab::make('Problémy s daty')
                ->badge(fn (): ?int => AdAlert::query()->visible()->where('category', AdAlert::DATA)->count() ?: null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('category', AdAlert::DATA)),
            AdAlert::PERFORMANCE => Tab::make('Výkon proti cíli')
                ->badge(fn (): ?int => AdAlert::query()->visible()->where('category', AdAlert::PERFORMANCE)->count() ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('category', AdAlert::PERFORMANCE)),
        ];
    }
}
