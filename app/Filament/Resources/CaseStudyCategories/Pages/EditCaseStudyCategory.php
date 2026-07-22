<?php

namespace App\Filament\Resources\CaseStudyCategories\Pages;

use App\Filament\Resources\CaseStudyCategories\CaseStudyCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCaseStudyCategory extends EditRecord
{
    protected static string $resource = CaseStudyCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
