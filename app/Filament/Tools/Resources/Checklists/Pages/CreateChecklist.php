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
        $template = $this->templateId
            ? Checklist::templates()->find($this->templateId)
            : null;

        $template?->copyStructureInto($this->record);

        $this->oznam($template !== null);
    }

    /** Shrnutí do jedné hlášky: co se zkopírovalo a kam se to dá poslat. */
    private function oznam(bool $predvyplneno): void
    {
        $radky = [];

        if ($predvyplneno) {
            $radky[] = 'Ze šablony se zkopírovalo '.$this->record->items()->count().' položek.';
        }

        if ($odkaz = $this->record->publicUrl()) {
            $radky[] = 'Odkaz pro klienta: '.$odkaz;
        }

        if (! $radky) {
            return;
        }

        Notification::make()
            ->success()
            ->title('Checklist je připravený')
            ->body(implode(' ', $radky))
            ->persistent()
            ->send();
    }
}
