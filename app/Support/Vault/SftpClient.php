<?php

namespace App\Support\Vault;

use App\Models\VaultEntry;
use phpseclib3\Net\SFTP;

/** SFTP (souborový přenos přes SSH) knihovnou phpseclib, bez rozšíření PHP. */
class SftpClient implements FtpClient
{
    private SFTP $sftp;

    private string $root;

    public function __construct(VaultEntry $entry)
    {
        $host = (string) preg_replace('~^s?ftps?://~i', '', trim((string) $entry->url, " /\t"));
        $port = (int) $entry->setting('port', 22) ?: 22;

        try {
            $sftp = new SFTP($host, $port, 20);
            $loggedIn = $sftp->login((string) $entry->username, (string) $entry->secret);
        } catch (\Throwable) {
            throw new VaultException("Na SFTP server {$host}:{$port} se nepodařilo připojit. Zkontroluj adresu a port (SFTP bývá 22).");
        }

        if (! $loggedIn) {
            $sftp->disconnect();

            throw new VaultException('SFTP server odmítl přihlášení. Zkontroluj uživatele a heslo.');
        }

        $this->sftp = $sftp;
        $this->root = (string) $entry->setting('root', '/');
    }

    public function list(string $path): array
    {
        $remote = VaultPath::join($this->root, $path);
        $items = $this->sftp->rawlist($remote);

        if (! is_array($items)) {
            throw new VaultException("Složka {$remote} na SFTP neexistuje nebo ji nejde přečíst.");
        }

        return collect($items)
            ->reject(fn ($item, $name): bool => in_array((string) $name, ['.', '..'], true))
            ->map(function (array $item, $name): ?array {
                $type = match ($item['type'] ?? null) {
                    NET_SFTP_TYPE_DIRECTORY => 'dir',
                    NET_SFTP_TYPE_REGULAR => 'file',
                    default => null,
                };

                return $type === null ? null : [
                    'name' => (string) $name,
                    'type' => $type,
                    'size' => $type === 'file' && isset($item['size']) ? (int) $item['size'] : null,
                    'modified' => isset($item['mtime']) ? date('Y-m-d H:i', (int) $item['mtime']) : null,
                ];
            })
            ->filter()
            ->sortBy(fn (array $item): string => ($item['type'] === 'dir' ? '0' : '1').$item['name'])
            ->values()
            ->all();
    }

    public function get(string $path): ?string
    {
        $remote = VaultPath::join($this->root, $path);

        if (! $this->sftp->is_file($remote)) {
            return null;
        }

        $contents = $this->sftp->get($remote);

        if ($contents === false) {
            throw new VaultException("Soubor {$path} se ze SFTP nepodařilo stáhnout.");
        }

        return $contents;
    }

    public function put(string $path, string $contents): void
    {
        $remote = VaultPath::join($this->root, $path);
        $directory = dirname($remote);

        if (! $this->sftp->is_dir($directory)) {
            $this->sftp->mkdir($directory, -1, true);
        }

        if (! $this->sftp->put($remote, $contents)) {
            throw new VaultException("Soubor {$path} se na SFTP nepodařilo nahrát (práva ke složce?).");
        }
    }

    public function delete(string $path): void
    {
        $remote = VaultPath::join($this->root, $path);

        if ($this->sftp->is_file($remote) && ! $this->sftp->delete($remote, false)) {
            throw new VaultException("Soubor {$path} se na SFTP nepodařilo smazat.");
        }
    }

    public function close(): void
    {
        $this->sftp->disconnect();
    }
}
