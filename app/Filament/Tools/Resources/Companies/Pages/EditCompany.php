<?php

namespace App\Filament\Tools\Resources\Companies\Pages;

use App\Filament\Tools\Actions\LogActivityAction;
use App\Filament\Tools\Actions\UseTemplateAction;
use App\Filament\Tools\Resources\Companies\CompanyResource;
use App\Models\Crm\Company;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

/**
 * Karta firmy. Všechno na jedné obrazovce: údaje nahoře, pod nimi kontakty,
 * obchody a časová osa. Zápis aktivity je z ní na jedno kliknutí, nebo
 * klávesou „n".
 */
class EditCompany extends EditRecord
{
    protected static string $resource = CompanyResource::class;

    /** Propásnutý follow-up je vidět už v nadpisu, ne až v tabulce níž. */
    public function getSubheading(): ?string
    {
        /** @var Company $company */
        $company = $this->getRecord();

        if ($company->isOverdue()) {
            return 'Follow-up měl proběhnout '.$company->next_action_at->format('j. n. Y').' — jsme po termínu.';
        }

        return $company->next_action_at !== null
            ? 'Další krok '.$company->next_action_at->format('j. n. Y').'.'
            : null;
    }

    protected function getHeaderActions(): array
    {
        $company = ['company' => $this->getRecord()->getKey()];

        return [
            LogActivityAction::make()
                ->arguments($company)
                ->keyBindings(['n']),

            UseTemplateAction::make()->arguments($company),

            Action::make('openWebsite')
                ->label('Otevřít web')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (Company $record): ?string => $record->websiteUrl(), shouldOpenInNewTab: true)
                ->visible(fn (Company $record): bool => $record->websiteUrl() !== null),

            DeleteAction::make(),
        ];
    }
}
