<?php

namespace App\Filament\Tools\Resources\Skills\Pages;

use App\Filament\Tools\Resources\Skills\SkillResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSkills extends ListRecords
{
    protected static string $resource = SkillResource::class;

    public function getSubheading(): ?string
    {
        return 'Specialisté, které agenti klientů volají (třeba /meta-ads). Každý má svého agenta v Projektech, kde se s ním probírá a učí, a v mozku vlastní know-how a archiv.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nový skill'),
        ];
    }
}
