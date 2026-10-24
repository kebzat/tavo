<?php

namespace App\Filament\Tools\Resources\KnowledgeEntries\Pages;

use App\Filament\Tools\Resources\KnowledgeEntries\KnowledgeEntryResource;
use App\Models\KnowledgeEntry;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

/** Úprava poznatku. U návrhu jde upravit a rovnou schválit. */
class EditKnowledgeEntry extends EditRecord
{
    protected static string $resource = KnowledgeEntryResource::class;

    public function getSubheading(): ?string
    {
        $record = $this->getRecord();

        return collect([
            $record->statusLabel(),
            KnowledgeEntry::originTypes()[$record->origin_type] ?? null,
            $record->proposed_by ? 'navrhl '.$record->proposed_by : null,
            $record->reviewed_by ? ($record->isProposed() ? null : 'posoudil '.$record->reviewed_by.' '.$record->reviewed_at?->format('j. n. Y')) : null,
        ])->filter()->implode(' · ');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return [...$data, 'client_id' => $data['layer'] === KnowledgeEntry::LAYER_CLIENT ? ($data['client_id'] ?? null) : null];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Uložit a schválit')
                ->icon(Heroicon::OutlinedCheck)
                ->color('success')
                ->visible(fn (): bool => $this->getRecord()->isProposed())
                ->action(function (): void {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    $this->getRecord()->approve(KnowledgeEntryResource::reviewer());
                    $this->redirect($this->getResource()::getUrl('index'));
                }),
            Action::make('reject')
                ->label('Zamítnout')
                ->icon(Heroicon::OutlinedXMark)
                ->color('gray')
                ->visible(fn (): bool => $this->getRecord()->isProposed())
                ->action(function (): void {
                    $this->getRecord()->reject(KnowledgeEntryResource::reviewer());
                    $this->redirect($this->getResource()::getUrl('index'));
                }),
            Action::make('archive')
                ->label('Do archivu')
                ->icon(Heroicon::OutlinedArchiveBox)
                ->color('gray')
                ->visible(fn (): bool => $this->getRecord()->status === KnowledgeEntry::STATUS_APPROVED)
                ->requiresConfirmation()
                ->modalDescription('Agenti ho přestanou dostávat. Z archivu jde vrátit.')
                ->action(function (): void {
                    $this->getRecord()->update(['status' => KnowledgeEntry::STATUS_ARCHIVED, 'reviewed_by' => KnowledgeEntryResource::reviewer(), 'reviewed_at' => now()]);
                    $this->redirect($this->getResource()::getUrl('index'));
                }),
            Action::make('restore')
                ->label('Vrátit do platnosti')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('gray')
                ->visible(fn (): bool => in_array($this->getRecord()->status, [KnowledgeEntry::STATUS_ARCHIVED, KnowledgeEntry::STATUS_REJECTED], true))
                ->action(function (): void {
                    $this->getRecord()->update(['status' => KnowledgeEntry::STATUS_APPROVED, 'reviewed_by' => KnowledgeEntryResource::reviewer(), 'reviewed_at' => now()]);
                    $this->refreshFormData(['status']);
                }),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
