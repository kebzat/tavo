<?php

namespace App\Filament\Tools\Actions;

use App\Filament\Tools\Resources\Audits\Pages\EditAudit;
use App\Models\Audit;
use App\Models\Crm\Company;
use App\Support\Crm\AuditFromCompany;
use App\Support\Crm\Scout\ProspectScout;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Audit s checklistem z karty firmy.
 *
 * Když firma ještě není proklepnutá, proklepne se napřed, audit bez
 * měření by neměl z čeho vzniknout. Audit se založí neveřejný a v omezeném
 * režimu a otevře se k přečtení. Sdílení se zapíná ručně, až ho někdo z nás
 * zkontroluje.
 */
class CreateAuditAction
{
    public static function make(): Action
    {
        return Action::make('createAudit')
            ->label('Vytvořit audit')
            ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
            ->visible(fn (Company $record): bool => $record->websiteUrl() !== null)
            ->requiresConfirmation()
            ->modalHeading('Vytvořit audit webu?')
            ->modalDescription(fn (Company $record): string => self::latestAudit($record) !== null
                ? 'Firma už audit má. Vznikne nový podle aktuálního měření, starý zůstane.'
                : 'Vznikne koncept auditu a checklist úkolů. Klientovi se nic neodešle, sdílení zapnete sami po kontrole.')
            ->modalSubmitActionLabel('Vytvořit')
            ->action(function (Company $record) {
                set_time_limit(240);

                if ($record->scout_data === null || ! ($record->scout_data['measurements']['reachable'] ?? false)) {
                    $record = app(ProspectScout::class)->scout($record);
                }

                if (! ($record->scout_data['measurements']['reachable'] ?? false)) {
                    Notification::make()
                        ->danger()
                        ->title('Web nejde načíst')
                        ->body($record->scout_data['measurements']['error'] ?? 'Audit nemá z čeho vzniknout.')
                        ->send();

                    return null;
                }

                $audit = app(AuditFromCompany::class)->create($record);

                Notification::make()
                    ->success()
                    ->title('Audit je připravený')
                    ->body('Přečtěte ho, upravte a pak zapněte sdílení.')
                    ->send();

                return redirect(EditAudit::getUrl(['record' => $audit]));
            });
    }

    /** Poslední audit firmy, pokud nějaký má. */
    public static function latestAudit(Company $company): ?Audit
    {
        return $company->client?->audits()->latest('id')->first();
    }
}
