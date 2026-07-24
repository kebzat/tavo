<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\OnlyForAdmins;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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
        ];
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
