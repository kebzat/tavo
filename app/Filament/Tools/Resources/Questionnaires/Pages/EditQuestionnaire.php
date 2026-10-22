<?php

namespace App\Filament\Tools\Resources\Questionnaires\Pages;

use App\Filament\Tools\Resources\Questionnaires\QuestionnaireResource;
use App\Models\Questionnaire;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditQuestionnaire extends EditRecord
{
    protected static string $resource = QuestionnaireResource::class;

    protected function getHeaderActions(): array
    {
        return [
            QuestionnaireResource::answersAction(),

            Action::make('open')
                ->label(fn (Questionnaire $record): string => $record->is_public ? 'Otevřít sdílený odkaz' : 'Náhled')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (Questionnaire $record): string => $record->previewUrl(), shouldOpenInNewTab: true),

            DeleteAction::make(),
        ];
    }
}
