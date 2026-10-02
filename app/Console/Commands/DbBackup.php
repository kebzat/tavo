<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Záloha databáze do storage/app/backups. Pouští ji nasazení před migracemi,
 * takže když migrace něco pokazí, je z čeho vrátit obsah z administrace.
 * Složka storage/ se při nasazení nepřepisuje (.github/deploy-exclude.txt).
 */
class DbBackup extends Command
{
    protected $signature = 'db:zaloha {--keep=10 : Kolik posledních záloh nechat}';

    protected $description = 'Uloží dump databáze do storage/app/backups (mysqldump + gzip)';

    public function handle(): int
    {
        $name = config('database.default');
        $db = config("database.connections.{$name}");

        if (! in_array($db['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            $this->error("Záloha umí jen MySQL/MariaDB, spojení „{$name}“ je {$db['driver']}.");

            return self::FAILURE;
        }

        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        $file = $dir.'/taveo-'.now()->format('Y-m-d-His').'.sql';

        $command = array_values(array_filter([
            'mysqldump',
            '--single-transaction',
            '--quick',
            '--no-tablespaces',
            '--default-character-set=utf8mb4',
            '--host='.$db['host'],
            '--port='.$db['port'],
            '--user='.$db['username'],
            empty($db['unix_socket']) ? null : '--socket='.$db['unix_socket'],
            '--result-file='.$file,
            $db['database'],
        ]));

        // Heslo přes proměnnou prostředí, ať není vidět ve výpisu procesů.
        $dump = Process::env(['MYSQL_PWD' => (string) $db['password']])->timeout(600)->run($command);

        if ($dump->failed() || ! File::exists($file) || File::size($file) === 0) {
            File::delete($file);
            $this->error('Záloha selhala: '.trim($dump->errorOutput() ?: $dump->output()));

            return self::FAILURE;
        }

        $gzip = Process::timeout(600)->run(['gzip', '-f', $file]);

        if ($gzip->failed()) {
            $this->error('Komprese zálohy selhala: '.trim($gzip->errorOutput()));

            return self::FAILURE;
        }

        $this->pruneOld($dir, max(1, (int) $this->option('keep')));
        $this->info('Záloha uložená: '.basename($file).'.gz ('.number_format(File::size($file.'.gz') / 1024, 0, ',', ' ').' kB)');

        return self::SUCCESS;
    }

    private function pruneOld(string $dir, int $keep): void
    {
        collect(File::glob($dir.'/taveo-*.sql.gz'))
            ->sortDesc()
            ->slice($keep)
            ->each(fn (string $old) => File::delete($old));
    }
}
