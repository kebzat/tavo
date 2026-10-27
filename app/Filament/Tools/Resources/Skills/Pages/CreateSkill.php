<?php

namespace App\Filament\Tools\Resources\Skills\Pages;

use App\Filament\Tools\Resources\Skills\SkillResource;
use App\Support\Agent\ClientProjects;
use Filament\Resources\Pages\CreateRecord;

/** Nový skill rovnou dostane agenta (projekt se založí na pozadí). */
class CreateSkill extends CreateRecord
{
    protected static string $resource = SkillResource::class;

    protected function afterCreate(): void
    {
        app(ClientProjects::class)->requestSkill($this->getRecord(), auth()->user()?->name);
    }
}
