<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Průvodce prvním spuštěním webu na serveru.
 *
 * Nahradí ruční příkazy v terminálu: zapíše .env, vygeneruje APP_KEY,
 * spustí migrace a naplní obsah. Počítá s tím, že `vendor/` a `public/build/`
 * jsou už nahrané (sestavené lokálně) — na serveru tedy composer ani npm netřeba.
 *
 * Jakmile je web nainstalovaný, průvodce se sám zamkne a vrací 404.
 */
class InstallController extends Controller
{
    /** Minimální verze PHP, se kterou projekt běží (viz composer.json). */
    private const MIN_PHP = '8.3.0';

    /** Rozšíření, bez kterých web nenaběhne. */
    private const REQUIRED_EXTENSIONS = [
        'mbstring', 'intl', 'gd', 'curl', 'zip', 'openssl', 'pdo_mysql',
    ];

    private const LOCK_FILE = 'installed.lock';

    public function show(): View
    {
        abort_if($this->isInstalled(), 404);

        return view('install.form', [
            'requirements' => $this->requirements(),
            'values' => $this->defaults(),
            'problems' => [],
        ]);
    }

    public function run(Request $request): View
    {
        abort_if($this->isInstalled(), 404);

        $values = $this->values($request);
        $requirements = $this->requirements();
        $problems = $this->problems($values, $requirements);

        if ($problems !== []) {
            return view('install.form', compact('requirements', 'values', 'problems'));
        }

        $key = $this->generateKey();
        $this->writeEnv($values, $key);
        $this->applyRuntimeConfig($values, $key);

        $steps = $this->runInstallSteps($values['seed']);

        $this->lock();

        return view('install.done', [
            'steps' => $steps,
            'adminUrl' => rtrim($values['app_url'], '/').'/admin',
        ]);
    }

    /**
     * Web je nainstalovaný, když existuje zámek, nebo když už má klíč
     * a proběhlé migrace. Druhá podmínka chrání weby nasazené ručně —
     * bez ní by na nich šel průvodce spustit znovu a přepsat konfiguraci.
     */
    private function isInstalled(): bool
    {
        if (is_file(storage_path('app/'.self::LOCK_FILE))) {
            return true;
        }

        if (blank(config('app.key'))) {
            return false;
        }

        try {
            return Schema::hasTable('migrations') && DB::table('migrations')->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    private function lock(): void
    {
        @mkdir(storage_path('app'), 0775, true);
        file_put_contents(storage_path('app/'.self::LOCK_FILE), now()->toIso8601String()."\n");
    }

    /**
     * @return array<string, array{label: string, ok: bool, detail: string}>
     */
    private function requirements(): array
    {
        $checks = [
            'php' => [
                'label' => 'PHP '.self::MIN_PHP.' nebo novější',
                'ok' => version_compare(PHP_VERSION, self::MIN_PHP, '>='),
                'detail' => PHP_VERSION,
            ],
        ];

        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            $checks['ext_'.$extension] = [
                'label' => 'Rozšíření '.$extension,
                'ok' => extension_loaded($extension),
                'detail' => extension_loaded($extension) ? 'k dispozici' : 'chybí',
            ];
        }

        foreach ([
            'kořen projektu (zápis .env)' => base_path(),
            'storage/' => storage_path(),
            'bootstrap/cache/' => base_path('bootstrap/cache'),
        ] as $label => $path) {
            $checks['write_'.$label] = [
                'label' => 'Zápis do '.$label,
                'ok' => is_writable($path),
                'detail' => is_writable($path) ? 'lze zapisovat' : 'nelze zapisovat',
            ];
        }

        return $checks;
    }

    /**
     * @return array<string, string|bool>
     */
    private function defaults(): array
    {
        return [
            'app_name' => (string) config('app.name', 'Taveo'),
            'app_url' => rtrim(request()->getSchemeAndHttpHost(), '/'),
            'db_host' => '127.0.0.1',
            'db_port' => '3306',
            'db_database' => '',
            'db_username' => '',
            'db_password' => '',
            'seed' => true,
        ];
    }

    /**
     * @return array<string, string|bool>
     */
    private function values(Request $request): array
    {
        return [
            'app_name' => trim((string) $request->input('app_name')),
            'app_url' => rtrim(trim((string) $request->input('app_url')), '/'),
            'db_host' => trim((string) $request->input('db_host')),
            'db_port' => trim((string) $request->input('db_port')),
            'db_database' => trim((string) $request->input('db_database')),
            'db_username' => trim((string) $request->input('db_username')),
            'db_password' => (string) $request->input('db_password'),
            'seed' => (bool) $request->boolean('seed'),
        ];
    }

    /**
     * @param  array<string, string|bool>  $values
     * @param  array<string, array{label: string, ok: bool, detail: string}>  $requirements
     * @return list<string>
     */
    private function problems(array $values, array $requirements): array
    {
        $problems = [];

        foreach ($requirements as $check) {
            if (! $check['ok']) {
                $problems[] = 'Nesplněný požadavek: '.$check['label'].' ('.$check['detail'].').';
            }
        }

        foreach ([
            'app_name' => 'Název webu',
            'app_url' => 'Adresa webu',
            'db_host' => 'Server databáze',
            'db_database' => 'Název databáze',
            'db_username' => 'Uživatel databáze',
        ] as $field => $label) {
            if ($values[$field] === '') {
                $problems[] = $label.' je povinný údaj.';
            }
        }

        if ($problems !== []) {
            return $problems;
        }

        if ($databaseProblem = $this->databaseProblem($values)) {
            $problems[] = $databaseProblem;
        }

        return $problems;
    }

    /**
     * @param  array<string, string|bool>  $values
     */
    private function databaseProblem(array $values): ?string
    {
        config(['database.connections._install' => $this->connectionConfig($values)]);

        try {
            DB::connection('_install')->getPdo();

            return null;
        } catch (\Throwable $e) {
            return 'Nepodařilo se připojit k databázi: '.$e->getMessage();
        } finally {
            DB::purge('_install');
        }
    }

    /**
     * @param  array<string, string|bool>  $values
     * @return array<string, mixed>
     */
    private function connectionConfig(array $values): array
    {
        return [
            'driver' => 'mysql',
            'host' => $values['db_host'],
            'port' => $values['db_port'] !== '' ? $values['db_port'] : '3306',
            'database' => $values['db_database'],
            'username' => $values['db_username'],
            'password' => $values['db_password'],
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ];
    }

    private function generateKey(): string
    {
        return 'base64:'.base64_encode(random_bytes(32));
    }

    /**
     * @param  array<string, string|bool>  $values
     */
    private function writeEnv(array $values, string $key): void
    {
        $path = base_path('.env');
        $content = is_file($path)
            ? (string) file_get_contents($path)
            : (string) file_get_contents(base_path('.env.example'));

        $replacements = [
            'APP_NAME' => $this->quote((string) $values['app_name']),
            'APP_ENV' => 'production',
            'APP_KEY' => $key,
            'APP_DEBUG' => 'false',
            'APP_URL' => (string) $values['app_url'],
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => (string) $values['db_host'],
            'DB_PORT' => $values['db_port'] !== '' ? (string) $values['db_port'] : '3306',
            'DB_DATABASE' => (string) $values['db_database'],
            'DB_USERNAME' => (string) $values['db_username'],
            'DB_PASSWORD' => $this->quote((string) $values['db_password']),
        ];

        foreach ($replacements as $envKey => $value) {
            $content = $this->setEnvValue($content, $envKey, $value);
        }

        file_put_contents($path, $content);
    }

    private function setEnvValue(string $content, string $key, string $value): string
    {
        $line = $key.'='.$value;
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

        // Callback, aby se $ a \ v hodnotě (typicky v hesle) nebraly jako zpětné reference.
        if (preg_match($pattern, $content) === 1) {
            return (string) preg_replace_callback($pattern, fn (): string => $line, $content, 1);
        }

        return rtrim($content, "\n")."\n".$line."\n";
    }

    private function quote(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return preg_match('/[\s"\'#]/', $value) === 1
            ? '"'.addcslashes($value, '"\\').'"'
            : $value;
    }

    /**
     * Nová konfigurace musí platit hned v tomhle requestu — migrace běží
     * ještě předtím, než se .env načte znovu.
     *
     * @param  array<string, string|bool>  $values
     */
    private function applyRuntimeConfig(array $values, string $key): void
    {
        config([
            'app.key' => $key,
            'database.default' => 'mysql',
            'database.connections.mysql' => $this->connectionConfig($values),
        ]);

        DB::purge('mysql');
    }

    /**
     * @return list<array{label: string, ok: bool, output: string}>
     */
    private function runInstallSteps(bool $seed): array
    {
        $steps = [];

        $steps[] = $this->step('Vytvoření tabulek (migrace)', fn () => Artisan::call('migrate', ['--force' => true]));

        if ($seed) {
            $steps[] = $this->step('Naplnění výchozího obsahu', fn () => Artisan::call('db:seed', ['--force' => true]));
        }

        $steps[] = $this->step('Propojení složky s nahranými soubory', fn () => Artisan::call('storage:link'));
        $steps[] = $this->step('Nacachování konfigurace', fn () => Artisan::call('optimize'));

        return $steps;
    }

    /**
     * @return array{label: string, ok: bool, output: string}
     */
    private function step(string $label, callable $callback): array
    {
        try {
            $callback();

            return ['label' => $label, 'ok' => true, 'output' => trim(Artisan::output())];
        } catch (\Throwable $e) {
            return ['label' => $label, 'ok' => false, 'output' => $e->getMessage()];
        }
    }
}
