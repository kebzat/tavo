<?php

namespace App\Filament\Tools\Resources\Checklists\Pages;

use App\Filament\Tools\Resources\Checklists\Actions\CreateFromTemplateAction;
use App\Filament\Tools\Resources\Checklists\ChecklistResource;
use App\Models\Checklist;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditChecklist extends EditRecord
{
    protected static string $resource = ChecklistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openPublicUrl')
                ->label('Otevřít sdílený odkaz')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (Checklist $record): ?string => $record->publicUrl(), shouldOpenInNewTab: true)
                ->visible(fn (Checklist $record): bool => $record->publicUrl() !== null),

            CreateFromTemplateAction::make()
                ->visible(fn (Checklist $record): bool => $record->is_template),

            DeleteAction::make(),
        ];
    }
}
