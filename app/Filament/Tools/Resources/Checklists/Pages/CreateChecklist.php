<?php

namespace App\Filament\Tools\Resources\Checklists\Pages;

use App\Filament\Tools\Resources\Checklists\ChecklistResource;
use App\Models\Checklist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateChecklist extends CreateRecord
{
    protected static string $resource = ChecklistResource::class;

    /** Vybraná šablona z formuláře. Není to sloupec, drží se jen do afterCreate(). */
    protected ?int $templateId = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->templateId = $data['template_id'] ?? null;
        unset($data['template_id']);

        return $data;
    }

    protected function afterCreate(): void
    {
        if (! $this->templateId) {
            return;
        }

        $template = Checklist::templates()->find($this->templateId);

        if (! $template) {
            return;
        }

        $template->copyStructureInto($this->record);

        Notification::make()
            ->success()
            ->title('Předvyplněno ze šablony')
            ->body('Zkopírovalo se '.$this->record->items()->count().' položek.')
            ->send();
    }
}
