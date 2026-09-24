<?php

namespace App\Filament\Tools\Resources\Audits\Pages;

use App\Filament\Tools\Resources\Audits\AuditResource;
use App\Models\Audit;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
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

            DeleteAction::make(),
        ];
    }
}
