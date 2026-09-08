<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\OnlyForAdmins;
use App\Settings\ContactSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Provozní úkony, které se jinak dělají v terminálu na serveru.
 * Doplňuje průvodce instalací — ten web rozjede, tahle stránka ho pak udržuje.
 */
class Maintenance extends Page
{
    use OnlyForAdmins;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $navigationLabel = 'Údržba';

    protected static ?string $title = 'Údržba webu';

    protected static string|\UnitEnum|null $navigationGroup = 'Nastavení';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.maintenance';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('migrate')
                ->label('Spustit migrace')
                ->icon(Heroicon::OutlinedCircleStack)
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Spustit migrace databáze?')
                ->modalDescription('Doplní chybějící tabulky a sloupce. Uložený obsah zůstane beze změny.')
                ->modalSubmitActionLabel('Spustit')
                ->action(fn () => $this->runArtisan('migrate', ['--force' => true], 'Migrace proběhly.')),

            Action::make('optimize')
                ->label('Obnovit cache')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Obnovit cache?')
                ->modalDescription('Zahodí a znovu vytvoří cache konfigurace, rout a šablon. Použijte, když se změny neprojevují.')
                ->modalSubmitActionLabel('Obnovit')
                ->action(function (): void {
                    $this->runArtisan('optimize:clear', [], 'Cache vyčištěna.', notify: false);
                    $this->runArtisan('optimize', [], 'Cache obnovena.');
                }),

            Action::make('testMail')
                ->label('Zkušební e-mail')
                ->icon(Heroicon::OutlinedEnvelope)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Poslat zkušební e-mail?')
                ->modalDescription('Odejde na stejné adresy jako poptávky z formuláře. Ověříte tím, že server e-maily opravdu posílá.')
                ->modalSubmitActionLabel('Odeslat')
                ->action(fn () => $this->sendTestMail()),

            Action::make('storageLink')
                ->label('Propojit soubory')
                ->icon(Heroicon::OutlinedLink)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Propojit složku s nahranými soubory?')
                ->modalDescription('Vytvoří veřejný odkaz na úložiště. Potřeba, když se nezobrazují nahrané obrázky.')
                ->modalSubmitActionLabel('Propojit')
                ->action(fn () => $this->runArtisan('storage:link', [], 'Složka propojena.')),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function systemInfo(): array
    {
        return [
            'PHP' => PHP_VERSION,
            'Laravel' => app()->version(),
            'Prostředí' => config('app.env').(config('app.debug') ? ' — ladicí režim zapnutý' : ''),
            'Databáze' => $this->databaseSummary(),
            'Poslední migrace' => $this->lastMigration(),
            'Odkaz na nahrané soubory' => is_link(public_path('storage')) ? 'propojeno' : 'chybí — použijte „Propojit soubory“',
            'Konfigurace' => $this->configSummary(),
            'Odesílání e-mailů' => $this->mailerSummary(),
            'Odesílatel' => (string) config('mail.from.address'),
            'Poptávky chodí na' => $this->recipientSummary(),
        ];
    }

    /**
     * Nasazení pouští `artisan optimize`, které si zapamatuje hodnoty z `.env`.
     * Když se `.env` na serveru změní až potom, aplikace o nové hodnotě neví,
     * dokud se cache neobnoví. Tohle je nejčastější důvod, proč „změnil jsem
     * .env a nic se nestalo“.
     */
    private function configSummary(): string
    {
        return app()->configurationIsCached()
            ? 'zacachovaná — po úpravě .env použijte „Obnovit cache“'
            : 'čte se přímo z .env';
    }

    /**
     * Nejčastější příčina „poptávky nechodí“: kanál zůstal na `log`, takže
     * zprávy končí v souboru s logem, nebo v `.env` chybí přihlášení k SMTP.
     */
    private function mailerSummary(): string
    {
        $mailer = (string) config('mail.default');

        return match ($mailer) {
            'log' => 'log — zprávy končí v souboru s logem, ne v e-mailu',
            'array' => 'array — zprávy se nikam neposílají',
            'smtp' => 'smtp — '.(config('mail.mailers.smtp.host') ?: 'chybí server').(config('mail.mailers.smtp.username') ? '' : ', bez přihlášení'),
            // K API transportu Resendu patří balíček resend/resend-php, který
            // v projektu není. Resend se tu posílá přes smtp.resend.com.
            'resend' => class_exists(\Resend::class)
                ? 'resend — přes API'
                : 'resend — NEFUNGUJE, chybí balíček resend/resend-php. Přepněte na smtp.resend.com, viz docs/DEPLOYMENT.md',
            default => $mailer,
        };
    }

    private function recipientSummary(): string
    {
        $recipients = app(ContactSettings::class)->recipientEmails();

        return $recipients === []
            ? 'nikam — doplňte příjemce v Nastavení → Kontakt'
            : implode(', ', $recipients);
    }

    /**
     * Zkušební zpráva na adresy, kam chodí poptávky. Chybu vypíšeme tak, jak
     * přišla ze serveru: bez ní se hledá naslepo.
     */
    private function sendTestMail(): void
    {
        $recipients = app(ContactSettings::class)->recipientEmails();

        if ($recipients === []) {
            Notification::make()
                ->title('Není komu poslat')
                ->body('Doplňte příjemce v Nastavení → Kontakt.')
                ->danger()
                ->send();

            return;
        }

        try {
            Mail::raw(
                'Zkušební zpráva z webu '.config('app.name').'. Když ji čtete, odesílání e-mailů funguje.'.PHP_EOL.PHP_EOL
                .'Odesláno: '.now()->format('j. n. Y H:i').PHP_EOL
                .'Odesílatel: '.config('mail.from.address').PHP_EOL
                .'Kanál: '.config('mail.default'),
                fn ($message) => $message->to($recipients)->subject('Zkušební e-mail z webu '.config('app.name')),
            );
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Odeslání selhalo')
                ->body(Str::limit($e->getMessage(), 300))
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        if (config('mail.default') === 'log') {
            Notification::make()
                ->title('Kanál je nastavený na „log“')
                ->body('Zpráva skončila v souboru s logem, do schránky nedorazí. Přepněte MAIL_MAILER na smtp.')
                ->warning()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title('Odesláno na '.implode(', ', $recipients))
            ->body('Když nic nedorazí ani do spamu, problém je na straně serveru nebo schránky.')
            ->success()
            ->send();
    }

    private function databaseSummary(): string
    {
        try {
            $name = DB::connection()->getDatabaseName();

            DB::connection()->getPdo();

            return $name.' — připojeno';
        } catch (\Throwable) {
            return 'nedostupná';
        }
    }

    private function lastMigration(): string
    {
        try {
            $migration = DB::table('migrations')->orderByDesc('id')->value('migration');

            return $migration !== null ? (string) $migration : 'žádná';
        } catch (\Throwable) {
            return 'neznámá';
        }
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function runArtisan(string $command, array $parameters, string $successMessage, bool $notify = true): void
    {
        try {
            Artisan::call($command, $parameters);

            if (! $notify) {
                return;
            }

            $output = trim(Artisan::output());

            Notification::make()
                ->title($successMessage)
                ->body($output !== '' ? Str::limit($output, 500) : null)
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Příkaz se nepodařilo dokončit')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }
}
