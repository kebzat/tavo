<?php

namespace App\Filament\Tools\Resources\AdReports\Pages;

use App\Enums\Ads\ReportStatus;
use App\Filament\Tools\Actions\Reviews;
use App\Filament\Tools\Pages\Ads\AdsClient;
use App\Filament\Tools\Resources\AdReports\AdReportResource;
use App\Mail\AdReportMail;
use App\Models\Ads\AdReport;
use App\Support\Ads\ReportBuilder;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Mail;

class EditAdReport extends EditRecord
{
    protected static string $resource = AdReportResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->title;
    }

    public function getSubheading(): ?string
    {
        /** @var AdReport $report */
        $report = $this->getRecord();

        return $report->client->name.($report->group ? ' · '.$report->group->name : '').' · '.match ($report->status) {
            ReportStatus::Draft => 'koncept ke kontrole, čísla jde ještě obnovit',
            ReportStatus::Approved => 'schváleno '.$report->approved_at?->format('j. n. Y H:i').($report->approver ? ' ('.$report->approver->name.')' : '').', čísla zamčená',
            ReportStatus::Sent => 'odesláno, čísla zamčená',
        };
    }

    /**
     * Upozornění nad formulářem: od zmrazení se čísla v datech změnila
     * (Meta dopočítala konverze). Report se nemění, jen o tom víme.
     */
    public function getHeader(): ?View
    {
        /** @var AdReport $report */
        $report = $this->getRecord();
        $drift = app(ReportBuilder::class)->drift($report);

        return $drift === [] ? null : view('filament.tools.partials.report-drift', ['drift' => $drift, 'report' => $report]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Reviews::action(),

            Action::make('preview')
                ->label('Náhled')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->url(fn (AdReport $record): string => $record->previewUrl(), shouldOpenInNewTab: true),

            Action::make('approve')
                ->label('Schválit')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->visible(fn (AdReport $record): bool => $record->status === ReportStatus::Draft)
                ->requiresConfirmation()
                ->modalHeading('Schválit report?')
                ->modalDescription('Čísla se zamknou tak, jak teď jsou. Když Meta později dopočítá konverze, report se nezmění. Odeslat ho můžete hned, nebo později.')
                ->action(function (AdReport $record, ReportBuilder $builder): void {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    $builder->approve($record);
                    Notification::make()->success()->title('Schváleno, čísla jsou zamčená')->send();
                }),

            Action::make('reopen')
                ->label('Vrátit ke kontrole')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('gray')
                ->visible(fn (AdReport $record): bool => $record->status === ReportStatus::Approved)
                ->action(function (AdReport $record, ReportBuilder $builder): void {
                    $builder->reopen($record);
                    Notification::make()->success()->title('Report je zase koncept')->send();
                }),

            // Meta konverze zpětně dopočítává. Před schválením se vyplatí čísla obnovit.
            Action::make('refresh')
                ->label('Obnovit čísla')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible(fn (AdReport $record): bool => $record->status === ReportStatus::Draft)
                ->requiresConfirmation()
                ->modalDescription('Čísla se přepočítají z toho, co je teď staženo. Komentář zůstane.')
                ->action(function (AdReport $record, ReportBuilder $builder): void {
                    $builder->refresh($record);
                    Notification::make()->success()->title('Čísla obnovena')->send();
                }),

            Action::make('send')
                ->label(fn (AdReport $record): string => $record->status === ReportStatus::Sent ? 'Odeslat znovu' : 'Odeslat klientovi')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->disabled(fn (AdReport $record, ReportBuilder $builder): bool => $builder->recipients($record->client) === [])
                ->requiresConfirmation()
                ->modalHeading('Odeslat report klientovi?')
                ->modalDescription(fn (AdReport $record, ReportBuilder $builder): string => 'E-mail s odkazem půjde na '
                    .implode(', ', $builder->recipients($record->client))
                    .'. Odkaz na report začne fungovat bez přihlášení.'
                    .(blank($record->summary) ? ' Report zatím nemá komentář.' : '')
                    .Reviews::warning($record))
                ->modalSubmitActionLabel('Odeslat')
                ->action(function (AdReport $record, ReportBuilder $builder): void {
                    // Neuložené úpravy komentáře nejdřív uložíme, ať klient dostane to, co je na obrazovce.
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);

                    $recipients = $builder->recipients($record->client);
                    $record->update(['is_public' => true]);

                    Mail::to($recipients)->send(new AdReportMail($record->refresh()));

                    // Odeslaný report je zároveň schválený, čísla se už nemění.
                    $record->update([
                        'status' => ReportStatus::Sent,
                        'sent_at' => now(),
                        'sent_to' => $recipients,
                        'approved_at' => $record->approved_at ?? now(),
                        'approved_by' => $record->approved_by ?? auth()->id(),
                    ]);

                    $this->refreshFormData(['is_public']);

                    Notification::make()->success()->title('Report odeslán')->body(implode(', ', $recipients))->send();
                }),

            ActionGroup::make([
                Action::make('client')
                    ->label('Detail klienta')
                    ->icon(Heroicon::OutlinedPresentationChartLine)
                    ->url(fn (AdReport $record): string => AdsClient::getUrl(['client' => $record->client_id, 'skupina' => $record->ad_campaign_group_id])),
                DeleteAction::make(),
            ])->label('Další')->button()->color('gray'),
        ];
    }
}
