<?php

namespace App\Filament\Tools\Resources\Audits\Pages;

use App\Filament\Tools\Resources\Audits\AuditResource;
use App\Jobs\WriteDeepAudit;
use App\Models\Audit;
use App\Support\Crm\Ai\ProspectAi;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditAudit extends EditRecord
{
    protected static string $resource = AuditResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openPublicUrl')
                ->label('Otevřít sdílený odkaz')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (Audit $record): ?string => $record->publicUrl(), shouldOpenInNewTab: true)
                ->visible(fn (Audit $record): bool => $record->publicUrl() !== null),

            // Znovu nechat Clauda projít web. Přepíše text, čísla i úkoly
            // v checklistu, proto s potvrzením.
            Action::make('deepAudit')
                ->label('Projít web Claudem')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('gray')
                ->visible(fn (Audit $record): bool => $record->client?->crmCompany !== null
                    && app(ProspectAi::class)->enabled()
                    && ! $record->isBeingWritten())
                ->requiresConfirmation()
                ->modalHeading('Nechat Clauda projít web a přepsat audit?')
                ->modalDescription('Přepíše text auditu, čísla pod hlavičkou i úkoly v checklistu. Ruční úpravy se ztratí. '
                    .'Trvá 3 až 6 minut a stojí řádově jednotky dolarů.')
                ->modalSubmitActionLabel('Spustit')
                ->action(function (Audit $record): void {
                    WriteDeepAudit::start($record);

                    Notification::make()
                        ->success()
                        ->title('Claude prochází web')
                        ->body('Za 3 až 6 minut obnovte stránku.')
                        ->send();
                }),

            DeleteAction::make(),
        ];
    }

    /** Rozepsaný audit se neukládá, přepsal by ho Claude a naopak. */
    public function getSubheading(): ?string
    {
        /** @var Audit $audit */
        $audit = $this->getRecord();

        return match (true) {
            $audit->isBeingWritten() => 'Claude právě prochází web a píše audit. Obnovte stránku za pár minut, do té doby nic neupravujte.',
            $audit->ai_status === 'done' => $audit->ai_note,
            $audit->ai_status === 'failed' => 'Podrobný audit se nepovedl: '.$audit->ai_note,
            default => null,
        };
    }
}
