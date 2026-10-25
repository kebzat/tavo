<?php

namespace App\Support\Vault;

/**
 * Spojení na FTP jednoho přístupu z trezoru. Cesty jsou vždy relativní
 * ke složce webu (nastavení přístupu `root`), ven z ní se nedostanou.
 * V testech se nahrazuje (FtpConnector::fake), skutečné je NativeFtpClient.
 */
interface FtpClient
{
    /** @return list<array{name: string, type: 'dir'|'file', size: int|null, modified: string|null}> */
    public function list(string $path): array;

    /** Obsah souboru, null když neexistuje. */
    public function get(string $path): ?string;

    /** Zapíše soubor, chybějící složky založí. */
    public function put(string $path, string $contents): void;

    public function delete(string $path): void;

    public function close(): void;
}
