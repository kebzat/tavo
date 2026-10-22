<?php

namespace App\Filament\Tools\Resources\Questionnaires\Pages;

use App\Filament\Tools\Resources\Questionnaires\QuestionnaireResource;
use Filament\Resources\Pages\CreateRecord;

class CreateQuestionnaire extends CreateRecord
{
    protected static string $resource = QuestionnaireResource::class;
}
