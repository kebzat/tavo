<?php

namespace App\Filament\Tools\Actions;

use App\Models\Crm\Company;
use App\Support\Crm\Scout\ProspectScout;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Proklepnutí webu z karty firmy. Měření trvá i půl minuty (PageSpeed),
 * proto běží hned a stránka na výsledek počká. Na dávky je příkaz
 * `crm:scout`, ten jede z plánovače.
 */
class ScoutCompanyAction
{
    public static function make(): Action
    {
        return Action::make('scoutCompany')
            ->label(fn (Company $record): string => $record->scouted_at ? 'Proklepnout znovu' : 'Proklepnout web')
            ->icon(Heroicon::OutlinedMagnifyingGlassCircle)
            ->color('gray')
            ->visible(fn (Company $record): bool => $record->websiteUrl() !== null)
            ->action(function (Company $record, $livewire): void {
                set_time_limit(180);

                $company = app(ProspectScout::class)->scout($record);

                Notification::make()
                    ->title($company->fit_verdict->getLabel().' · '.$company->fit_score.' bodů')
                    ->body(count($company->scout_data['findings'] ?? []).' nálezů. Podrobnosti v bloku Posouzení.')
                    ->color($company->fit_verdict->getColor())
                    ->success()
                    ->send();

                // Formulář drží stará data. Bez obnovení by uložení karty
                // vrátilo platformu a bolest do stavu před proklepnutím.
                $livewire->refreshFormData(['platform', 'pain']);
            });
    }
}
