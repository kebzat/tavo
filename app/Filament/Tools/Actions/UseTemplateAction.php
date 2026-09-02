<?php

namespace App\Filament\Tools\Actions;

use App\Models\Crm\Company;
use App\Models\Crm\MessageTemplate;
use App\Support\Crm\TemplateRenderer;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * Předvyplnění zprávy z šablony.
 *
 * Text se vyskládá s dosazenými údaji firmy, ukáže se k překopírování do
 * pošty nebo LinkedInu a jedním potvrzením se rovnou zapíše jako aktivita.
 * Bez toho posledního kroku by se zprávy posílaly a v CRM nezůstala stopa.
 */
class UseTemplateAction
{
    public static function make(string $name = 'useTemplate'): Action
    {
        return Action::make($name)
            ->label('Použít šablonu')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('gray')
            ->modalHeading('Zpráva ze šablony')
            ->modalDescription('Klepnutím na předmět nebo text se obsah zkopíruje do schránky.')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel('Zapsat jako aktivitu')
            // Schéma staví akce, takže jí sem Filament podá arguments i record.
            // Komponenty uvnitř už by se k firmě nedostaly, proto se resolvuje
            // jednou tady a uzavře se do closure jednotlivých polí.
            ->schema(function (array $arguments, ?Model $record): array {
                $company = LogActivityAction::resolveCompany($arguments, $record);

                return [
                    Select::make('template_id')
                        ->label('Šablona')
                        ->options(fn (): array => MessageTemplate::active()
                            ->orderBy('channel')
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn (MessageTemplate $t): array => [
                                $t->getKey() => $t->channel->getLabel().' — '.$t->name,
                            ])
                            ->all())
                        ->native(false)
                        ->required()
                        ->live(),

                    TextEntry::make('preview_subject')
                        ->label('Předmět')
                        ->copyable()
                        ->copyMessage('Předmět zkopírován')
                        ->state(fn (Get $get): ?string => self::rendered($get, $company)['subject'])
                        ->visible(fn (Get $get): bool => filled(self::rendered($get, $company)['subject'])),

                    TextEntry::make('preview_body')
                        ->label('Text')
                        ->copyable()
                        ->copyMessage('Text zkopírován')
                        // Šablony píšeme jako odstavce, takže zalomení musí přežít
                        // až do náhledu. Jinak se z pěti vět stane jedna kaše.
                        ->html()
                        ->state(fn (Get $get): ?string => filled($body = self::rendered($get, $company)['body'])
                            ? nl2br(e($body))
                            : null)
                        // Do schránky patří původní text, ne to, co se vykresluje.
                        // Bez tohohle by se do pošty vložilo „<br />" místo zalomení.
                        ->copyableState(fn (Get $get): ?string => self::rendered($get, $company)['body'])
                        ->visible(fn (Get $get): bool => filled(self::rendered($get, $company)['body'])),

                    ...self::followUpFields(),
                ];
            })
            ->action(function (array $data, array $arguments, ?Model $record): void {
                $company = LogActivityAction::resolveCompany($arguments, $record);
                $template = MessageTemplate::findOrFail($data['template_id']);
                $rendered = TemplateRenderer::render($template, $company);

                LogActivityAction::log($company, [
                    'type' => $template->channel->toActivityType(),
                    'subject' => $rendered['subject'] ?: $template->name,
                    'body' => $rendered['body'],
                    'happened_at' => now(),
                    'follow_up' => $data['follow_up'] ?? 'none',
                    'follow_up_at' => $data['follow_up_at'] ?? null,
                ]);

                Notification::make()
                    ->success()
                    ->title('Zapsáno jako aktivita')
                    ->body($template->name)
                    ->send();
            });
    }

    /**
     * Follow-up se plánuje stejně jako u ruční aktivity, jen bez výsledku.
     * Ten u právě odeslané zprávy ještě neznáme.
     *
     * @return array<int, mixed>
     */
    private static function followUpFields(): array
    {
        return array_values(array_filter(
            LogActivityAction::schema(),
            fn ($field): bool => in_array($field->getName(), ['follow_up', 'follow_up_at'], true),
        ));
    }

    /**
     * Vyskládaný text pro náhled.
     *
     * @return array{subject: ?string, body: ?string}
     */
    private static function rendered(Get $get, Company $company): array
    {
        $template = filled($get('template_id')) ? MessageTemplate::find($get('template_id')) : null;

        if ($template === null) {
            return ['subject' => null, 'body' => null];
        }

        return TemplateRenderer::render($template, $company);
    }
}
