<?php

namespace App\Filament\Tools\Resources\Proposals\Pages;

use App\Filament\Tools\Actions\Reviews;
use App\Filament\Tools\Resources\Proposals\ProposalResource;
use App\Models\Proposal;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditProposal extends EditRecord
{
    protected static string $resource = ProposalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Reviews::action(),

            Action::make('openPublicUrl')
                ->label('Otevřít sdílený odkaz')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (Proposal $record): ?string => $record->publicUrl(), shouldOpenInNewTab: true)
                ->visible(fn (Proposal $record): bool => $record->publicUrl() !== null),

            Action::make('preview')
                ->label('Náhled')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->url(fn (Proposal $record): ?string => $record->previewUrl(), shouldOpenInNewTab: true)
                ->visible(fn (Proposal $record): bool => $record->publicUrl() === null && $record->previewUrl() !== null),

            DeleteAction::make(),
        ];
    }
}
