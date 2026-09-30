<?php

namespace App\Filament\Tools\Resources\AdReports\Pages;

use App\Enums\Ads\ReportType;
use App\Filament\Tools\Resources\AdReports\AdReportResource;
use App\Models\Client;
use App\Support\Ads\Period;
use App\Support\Ads\ReportBuilder;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAdReport extends CreateRecord
{
    protected static string $resource = AdReportResource::class;

    /** Report nevzniká z formuláře, ale z čísel za období. */
    protected function handleRecordCreation(array $data): Model
    {
        return app(ReportBuilder::class)->create(
            Client::findOrFail($data['client_id']),
            ReportType::from($data['type'] instanceof ReportType ? $data['type']->value : $data['type']),
            Period::between($data['period_start'], $data['period_end']),
        );
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
