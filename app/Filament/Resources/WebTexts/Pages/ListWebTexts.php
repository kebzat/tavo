<?php

namespace App\Filament\Resources\WebTexts\Pages;

use App\Filament\Resources\WebTexts\WebTextResource;
use Filament\Resources\Pages\ListRecords;

class ListWebTexts extends ListRecords
{
    protected static string $resource = WebTextResource::class;

    // Bez tohohle by Filament z „Statické texty“ udělal „Statické Texty“.
    protected static ?string $title = 'Statické texty';

    public function getSubheading(): ?string
    {
        return 'Texty se sem zapisují samy, jakmile je web poprvé vykreslí. Co tu není, má vlastní pole v nastavení nebo v obsahu.';
    }
}
