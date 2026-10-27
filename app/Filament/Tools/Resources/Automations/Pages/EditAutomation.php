<?php

namespace App\Filament\Tools\Resources\Automations\Pages;

use App\Filament\Tools\Resources\Automations\AutomationResource;
use App\Models\Automation;
use App\Models\AutomationEvent;
use App\Support\Automations\ParcelPickup;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditAutomation extends EditRecord
{
    protected static string $resource = AutomationResource::class;

    public function getTitle(): string
    {
        return 'Automatizace: '.$this->getRecord()->client?->name;
    }

    /** Adresa exportu je v modelu skrytá (`$hidden`), do formuláře ji správce vidět smí. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, 'order_export_url' => $this->getRecord()->order_export_url];
    }

    /** Uložené nastavení se slučuje s výchozím, ať se klíče mimo formulář neztratí. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['settings'] = array_replace(Automation::DEFAULT_SETTINGS, $this->getRecord()->settings ?? [], $data['settings'] ?? []);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('dryRun')
                ->label('Vyzkoušet nanečisto')
                ->icon(Heroicon::OutlinedBeaker)
                ->color('gray')
                ->action(function (Automation $record): void {
                    $report = app(ParcelPickup::class)->run($record->replicate()->forceFill(['enabled' => true, 'id' => $record->getKey()]), dryRun: true);

                    if ($report['error'] !== null) {
                        Notification::make()->danger()->title('Nefunguje')->body($report['error'])->persistent()->send();

                        return;
                    }

                    $lines = collect($report['created'])->map(fn (array $event): string => $event['order'].': '.AutomationEvent::kindLabel($event['kind']));

                    Notification::make()->success()->title('Funguje, nic se neuložilo')
                        ->body('Na výdejních místech '.count($report['parcels']).'. '.($lines->isEmpty() ? 'Teď by se nic neposlalo.' : 'Poslalo by se: '.$lines->implode(', ').'.')
                            .($report['warnings'] ? ' '.implode(' ', $report['warnings']) : ''))
                        ->persistent()
                        ->send();
                }),
            Action::make('newToken')
                ->label('Nová adresa CSV')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Stará adresa přestane fungovat. Novou je potřeba vložit do importu v Upgates.')
                ->action(function (Automation $record): void {
                    $record->update(['import_token' => Automation::newToken()]);
                    Notification::make()->success()->title('Nová adresa je níž, vlož ji do Upgates')->send();
                }),
            DeleteAction::make(),
        ];
    }
}
