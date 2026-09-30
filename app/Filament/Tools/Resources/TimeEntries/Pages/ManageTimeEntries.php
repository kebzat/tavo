<?php

namespace App\Filament\Tools\Resources\TimeEntries\Pages;

use App\Filament\Tools\Actions\Ads\LogTimeAction;
use App\Filament\Tools\Resources\TimeEntries\TimeEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Model;

class ManageTimeEntries extends ManageRecords
{
    protected static string $resource = TimeEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Zapsat čas')
                ->modalHeading('Zapsat čas')
                ->using(fn (array $data): Model => LogTimeAction::create($data)),
        ];
    }
}
