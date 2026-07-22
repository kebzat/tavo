<?php

namespace App\Filament\Resources\CaseStudyCategories\Pages;

use App\Filament\Resources\CaseStudyCategories\CaseStudyCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCaseStudyCategories extends ListRecords
{
    protected static string $resource = CaseStudyCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
