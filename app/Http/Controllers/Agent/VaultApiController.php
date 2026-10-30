<?php

namespace App\Http\Controllers\Agent;

use App\Enums\VaultType;
use App\Http\Controllers\Controller;
use App\Models\VaultEntry;
use App\Models\VaultUpload;
use App\Support\Vault\EcomailApi;
use App\Support\Vault\FtpConnector;
use App\Support\Vault\FtpUploader;
use App\Support\Vault\FtpZipper;
use App\Support\Vault\LoginBroker;
use App\Support\Vault\VaultException;
use App\Support\Vault\VaultPath;
use App\Support\Vault\WordPressApi;
use App\Support\Vault\ZaslatApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trezor pro agenta (token agenta, příkazy `taveo ftp…`, `taveo wp`,
 * `taveo zaslat`, `taveo ecomail`, `taveo prihlasit`). Agent vidí, jaké přístupy má, a práci s nimi za něj
 * dělá web. Heslo, klíč ani poznámku žádná odpověď neobsahuje. Každé
 * použití jde do historie přístupu. Viz docs/TREZOR.md.
 */
class VaultApiController extends Controller
{
    private const JSON = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    private const ACTOR = 'agent';

    /** Přístupy, které agent smí použít: `?klient=slug`. */
    public function index(Request $request): JsonResponse
    {
        $entries = VaultEntry::query()
            ->forAgent()
            ->with('client:id,slug')
            ->when($request->filled('klient'), fn ($query) => $query->whereHas('client', fn ($query) => $query->where('slug', $request->string('klient'))))
            ->orderBy('client_id')->orderBy('name')
            ->get();

        return response()->json([
            'pristupy' => $entries->map(fn (VaultEntry $entry): array => $entry->toAgent())->values(),
            'poznamka' => 'Hesla agent nedostane. Přístupy použij příkazy v „umi“, nové přidá člověk v Nástrojích → Trezor.',
        ], options: self::JSON);
    }

    public function ftpList(Request $request, VaultEntry $entry, FtpConnector $connector): Response
    {
        $this->allow($entry, 'read', VaultType::Ftp);
        $path = VaultPath::normalize((string) $request->query('cesta', ''));

        return $this->attempt($entry, 'ftp_list', '/'.$path, function () use ($connector, $entry, $path) {
            $ftp = $connector->open($entry);

            try {
                return response()->json(['cesta' => '/'.$path, 'polozky' => $ftp->list($path)], options: self::JSON);
            } finally {
                $ftp->close();
            }
        });
    }

    public function ftpFile(Request $request, VaultEntry $entry, FtpConnector $connector): Response
    {
        $this->allow($entry, 'read', VaultType::Ftp);
        $path = VaultPath::normalize((string) $request->query('cesta', ''));

        return $this->attempt($entry, 'ftp_read', '/'.$path, function () use ($connector, $entry, $path) {
            $ftp = $connector->open($entry);

            try {
                $contents = $ftp->get($path);
            } finally {
                $ftp->close();
            }

            abort_if($contents === null, 404, 'Soubor na FTP neexistuje.');

            return response($contents, 200, ['Content-Type' => 'application/octet-stream']);
        });
    }

    /** Složka jako ZIP, průběžně. `?cesta=wp-content/themes&vynechat[]=…&bez_vychozich=1`. */
    public function ftpZip(Request $request, VaultEntry $entry, FtpConnector $connector): Response
    {
        $this->allow($entry, 'read', VaultType::Ftp);
        $path = VaultPath::normalize((string) $request->query('cesta', ''));
        $excludes = [
            ...($request->boolean('bez_vychozich') ? [] : FtpZipper::DEFAULT_EXCLUDES),
            ...array_map(VaultPath::normalize(...), (array) $request->query('vynechat', [])),
        ];

        // Spojení se otevře předem, ať špatné heslo vrátí srozumitelnou chybu, ne rozbitý ZIP.
        $ftp = $this->attempt($entry, 'ftp_zip', '/'.$path, fn () => $connector->open($entry));

        if ($ftp instanceof JsonResponse) {
            return $ftp;
        }

        $name = ($path === '' ? 'web' : basename($path)).'.zip';

        return response()->stream(function () use ($ftp, $path, $excludes) {
            set_time_limit(0);

            try {
                (new FtpZipper($ftp, $excludes))->write($path, fopen('php://output', 'wb'));
            } finally {
                $ftp->close();
            }
        }, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'X-Accel-Buffering' => 'no',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** multipart: `soubory[]` + `cesty[]` (cesta od složky webu) + `popis`. */
    public function ftpUpload(Request $request, VaultEntry $entry, FtpUploader $uploader): Response
    {
        $this->allow($entry, 'approve', VaultType::Ftp);

        $data = $request->validate([
            'popis' => ['nullable', 'string', 'max:2000'],
            'soubory' => ['required', 'array', 'max:'.FtpUploader::MAX_FILES],
            'soubory.*' => ['file'],
            'cesty' => ['required', 'array', 'size:'.count((array) $request->file('soubory', []))],
            'cesty.*' => ['required', 'string', 'max:500'],
        ]);

        $files = [];

        foreach ($request->file('soubory') as $i => $file) {
            $files[$data['cesty'][$i]] = (string) file_get_contents($file->getRealPath());
        }

        return $this->attempt($entry, null, null, function () use ($uploader, $entry, $files, $data) {
            $upload = $uploader->request($entry, $files, $data['popis'] ?? null, self::ACTOR);

            return response()->json($this->uploadState($upload), options: self::JSON);
        });
    }

    public function upload(VaultEntry $entry, VaultUpload $upload): JsonResponse
    {
        $this->allow($entry, 'read', VaultType::Ftp);
        abort_unless($upload->vault_entry_id === $entry->getKey(), 404);

        return response()->json($this->uploadState($upload), options: self::JSON);
    }

    /** `{"metoda": "GET", "cesta": "/wp/v2/plugins", "dotaz": {}, "data": {}}` */
    public function wordpress(Request $request, VaultEntry $entry, WordPressApi $api): Response
    {
        $this->allow($entry, 'read', VaultType::WordPress);

        $data = $request->validate([
            'metoda' => ['nullable', 'string', 'max:10'],
            'cesta' => ['required', 'string', 'max:500'],
            'dotaz' => ['nullable', 'array'],
            'data' => ['nullable', 'array'],
        ]);
        $method = strtoupper($data['metoda'] ?? 'GET');

        return $this->attempt($entry, 'wp_api', $method.' '.$data['cesta'], function () use ($api, $entry, $method, $data) {
            $response = $api->call($entry, $method, $data['cesta'], $data['dotaz'] ?? [], $data['data'] ?? []);

            return response($response->body(), $response->status(), ['Content-Type' => $response->header('Content-Type') ?: 'application/json']);
        });
    }

    /** `{"metoda": "GET", "cesta": "/shipments/list?tracking", "dotaz": {}, "data": {}}`, jen čtení. */
    public function zaslat(Request $request, VaultEntry $entry, ZaslatApi $api): Response
    {
        $this->allow($entry, 'read', VaultType::ApiKey);
        abort_unless(ZaslatApi::supports($entry), 422, 'Tenhle klíč není k Zaslatu (adresa u přístupu musí být zaslat.cz).');

        $data = $request->validate([
            'metoda' => ['nullable', 'string', 'max:10'],
            'cesta' => ['required', 'string', 'max:500'],
            'dotaz' => ['nullable', 'array'],
            'data' => ['nullable', 'array'],
        ]);
        $method = strtoupper($data['metoda'] ?? 'GET');

        return $this->attempt($entry, 'zaslat_api', $method.' '.$data['cesta'], function () use ($api, $entry, $method, $data) {
            $response = $api->call($entry, $method, $data['cesta'], $data['dotaz'] ?? [], $data['data'] ?? []);

            return response($response->body(), $response->status(), ['Content-Type' => $response->header('Content-Type') ?: 'application/json']);
        });
    }

    /** `{"metoda": "GET", "cesta": "/lists", "dotaz": {}, "data": {}}`. Změny jen s „write“ a bez odesílání (EcomailApi). */
    public function ecomail(Request $request, VaultEntry $entry, EcomailApi $api): Response
    {
        $data = $request->validate([
            'metoda' => ['nullable', 'string', 'max:10'],
            'cesta' => ['required', 'string', 'max:500'],
            'dotaz' => ['nullable', 'array'],
            'data' => ['nullable', 'array'],
        ]);
        $method = strtoupper($data['metoda'] ?? 'GET');
        $write = $method !== 'GET';

        $this->allow($entry, $write ? 'write' : 'read', VaultType::ApiKey);
        abort_unless(EcomailApi::supports($entry), 422, 'Tenhle klíč není k Ecomailu (adresa u přístupu musí být ecomail.cz).');

        return $this->attempt($entry, $write ? 'ecomail_change' : 'ecomail_api', $method.' '.$data['cesta'], function () use ($api, $entry, $method, $data) {
            $response = $api->call($entry, $method, $data['cesta'], $data['dotaz'] ?? [], $data['data'] ?? []);

            return response($response->body(), $response->status(), ['Content-Type' => $response->header('Content-Type') ?: 'application/json']);
        });
    }

    /** Přihlásí se do administrace a vrátí cookies pro prohlížeč agenta (formát Playwrightu). */
    public function login(VaultEntry $entry, LoginBroker $broker): JsonResponse
    {
        $this->allow($entry, 'read');
        abort_unless($entry->type->isLogin(), 422, 'Tenhle přístup se přihlašovacím formulářem nepoužívá.');

        try {
            $cookies = $broker->login($entry);
        } catch (VaultException $e) {
            $entry->record(self::ACTOR, 'login_failed', $e->getMessage());

            return response()->json(['ok' => false, 'chyba' => $e->getMessage()], 422, options: self::JSON);
        }

        $entry->record(self::ACTOR, 'login', $broker->resumed ? 'Platná relace z minula, bez nového přihlášení' : $entry->loginUrl());

        return response()->json([
            'ok' => true,
            'adresa' => $entry->adminUrl() ?? rtrim((string) $entry->url, '/'),
            'cookies' => $cookies,
            'navod' => 'V prohlížeči: browser_run_code_unsafe s `await page.context().addCookies(cookies)` a pak browser_navigate na adresu.',
        ], options: self::JSON);
    }

    private function allow(VaultEntry $entry, string $level, ?VaultType $type = null): void
    {
        if ($type !== null && $entry->type !== $type) {
            abort(422, 'Tenhle přístup je '.$entry->type->getLabel().', ne '.$type->getLabel().'.');
        }

        if (! $entry->agentAllows($level)) {
            $entry->record(self::ACTOR, 'denied', 'Chtěl: '.$level);

            abort(403, 'Tohle agent u přístupu „'.$entry->name.'“ nesmí. Změnit to může člověk v Nástrojích → Trezor („Co smí agent“).');
        }
    }

    /** Provede práci, zapíše ji do historie a chybu trezoru vrátí jako JSON se srozumitelným textem. */
    private function attempt(VaultEntry $entry, ?string $action, ?string $detail, callable $work): mixed
    {
        try {
            $result = $work();
        } catch (VaultException $e) {
            return response()->json(['ok' => false, 'chyba' => $e->getMessage()], 422, options: self::JSON);
        }

        if ($action !== null) {
            $entry->record(self::ACTOR, $action, $detail);
        }

        return $result;
    }

    private function uploadState(VaultUpload $upload): array
    {
        return array_filter([
            'nahrani' => $upload->getKey(),
            'stav' => $upload->statusLabel(),
            'soubory' => collect($upload->files)->pluck('path')->all(),
            'chyba' => $upload->error,
            'dalsi_krok' => $upload->status === VaultUpload::STATUS_PENDING
                ? 'Čeká, až ho člověk schválí v Nástrojích → Trezor (u přístupu, záložka Nahrání). Napiš mu, co a proč se mění.'
                : null,
        ]);
    }
}
