<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use PDO;
use Throwable;

/**
 * Záloha databáze do storage/app/backups. Pouští ji nasazení před migracemi,
 * takže když migrace něco pokazí, je z čeho vrátit obsah z administrace.
 * Složka storage/ se při nasazení nepřepisuje (.github/deploy-exclude.txt).
 *
 * Nejdřív zkusí mysqldump. Na sdíleném hostingu ale nemusí projít (jiná
 * oprávnění klienta, chybějící binárka), proto je druhá cesta dump přes
 * spojení aplikace: když web s databází mluví, záloha se udělá taky.
 */
class DbBackup extends Command
{
    protected $signature = 'db:zaloha
        {--keep=10 : Kolik posledních záloh nechat}
        {--php : Rovnou dump přes spojení aplikace, bez mysqldump}';

    protected $description = 'Uloží dump databáze do storage/app/backups (.sql.gz)';

    /** Kolik řádků jde do jednoho INSERTu a jednoho dotazu. */
    private const CHUNK = 500;

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
        $file = $dir.'/taveo-'.now()->format('Y-m-d-His').'.sql.gz';

        $method = 'mysqldump';
        $error = $this->option('php') ? 'vynecháno (--php)' : $this->mysqldump($db, $file);

        if ($error !== null) {
            $method = 'PHP';

            try {
                $this->phpDump($name, $file);
            } catch (Throwable $e) {
                File::delete($file);
                $this->error("Záloha selhala. mysqldump: {$error}. PHP: {$e->getMessage()}");

                return self::FAILURE;
            }
        }

        $this->pruneOld($dir, max(1, (int) $this->option('keep')));
        $this->info('Záloha uložená ('.$method.'): '.basename($file).' ('.number_format(File::size($file) / 1024, 0, ',', ' ').' kB)');

        return self::SUCCESS;
    }

    /**
     * Dump přes mysqldump, výstup zkomprimuje PHP (gzip binárka není nutná).
     *
     * @param  array<string, mixed>  $db
     * @return string|null chyba, nebo `null`, když se dump povedl
     */
    private function mysqldump(array $db, string $file): ?string
    {
        $sql = substr($file, 0, -3);

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
            '--result-file='.$sql,
            $db['database'],
        ]));

        try {
            // Heslo přes proměnnou prostředí, ať není vidět ve výpisu procesů.
            $dump = Process::env(['MYSQL_PWD' => (string) $db['password']])->timeout(600)->run($command);
        } catch (Throwable $e) {
            File::delete($sql);

            return $e->getMessage();
        }

        if ($dump->failed() || ! File::exists($sql) || File::size($sql) === 0) {
            File::delete($sql);

            return trim($dump->errorOutput() ?: $dump->output()) ?: 'prázdný výstup';
        }

        $in = fopen($sql, 'rb');
        $out = gzopen($file, 'wb6');

        while (! feof($in)) {
            gzwrite($out, (string) fread($in, 1024 * 1024));
        }

        fclose($in);
        gzclose($out);
        File::delete($sql);

        return null;
    }

    /**
     * Dump přes spojení aplikace: struktura (SHOW CREATE TABLE) a data
     * po dávkách jako INSERTy. Obnova stejně jako u mysqldump:
     * `gunzip -c soubor.sql.gz | mysql -u … -p … databáze`.
     */
    private function phpDump(string $connection, string $file): void
    {
        $pdo = DB::connection($connection)->getPdo();
        $out = gzopen($file, 'wb6');

        $write = fn (string $line) => gzwrite($out, $line."\n");

        $write('-- Záloha TAVEO přes PHP, '.now()->toDateTimeString());
        $write('SET NAMES utf8mb4;');
        $write('SET FOREIGN_KEY_CHECKS=0;');
        $write("SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';");

        $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);

        foreach (array_column($tables, 0) as $table) {
            $quoted = '`'.str_replace('`', '``', $table).'`';
            $create = $pdo->query("SHOW CREATE TABLE {$quoted}")->fetch(PDO::FETCH_NUM)[1];

            $write('');
            $write("DROP TABLE IF EXISTS {$quoted};");
            $write($create.';');

            $offset = 0;

            do {
                $rows = $pdo->query("SELECT * FROM {$quoted} LIMIT ".self::CHUNK." OFFSET {$offset}")->fetchAll(PDO::FETCH_ASSOC);

                if ($rows !== []) {
                    $columns = implode(', ', array_map(fn ($column) => '`'.str_replace('`', '``', $column).'`', array_keys($rows[0])));
                    $values = array_map(
                        fn (array $row) => '('.implode(', ', array_map(fn ($value) => $value === null ? 'NULL' : $pdo->quote((string) $value), $row)).')',
                        $rows,
                    );

                    $write("INSERT INTO {$quoted} ({$columns}) VALUES\n".implode(",\n", $values).';');
                }

                $offset += self::CHUNK;
            } while (count($rows) === self::CHUNK);
        }

        $write('');
        $write('SET FOREIGN_KEY_CHECKS=1;');
        gzclose($out);
    }

    private function pruneOld(string $dir, int $keep): void
    {
        collect(File::glob($dir.'/taveo-*.sql.gz'))
            ->sortDesc()
            ->slice($keep)
            ->each(fn (string $old) => File::delete($old));
    }
}
