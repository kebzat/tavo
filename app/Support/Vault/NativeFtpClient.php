<?php

namespace App\Support\Vault;

use App\Models\VaultEntry;
use FTP\Connection;

/** FTP a FTPS přes rozšíření PHP `ftp`, pasivní režim. */
class NativeFtpClient implements FtpClient
{
    private Connection $ftp;

    private string $root;

    public function __construct(VaultEntry $entry)
    {
        if (! function_exists('ftp_connect')) {
            throw new VaultException('Na serveru chybí rozšíření PHP „ftp“, FTP z trezoru zatím nejde.');
        }

        $host = (string) preg_replace('~^s?ftps?://~i', '', trim((string) $entry->url, " /\t"));
        $port = (int) $entry->setting('port', 21) ?: 21;
        $connection = $entry->setting('tls', true)
            ? @ftp_ssl_connect($host, $port, 20)
            : @ftp_connect($host, $port, 20);

        if (! $connection) {
            throw new VaultException("Na FTP server {$host}:{$port} se nepodařilo připojit.");
        }

        if (! @ftp_login($connection, (string) $entry->username, (string) $entry->secret)) {
            @ftp_close($connection);

            throw new VaultException(
                'FTP server odmítl přihlášení. Zkontroluj uživatele a heslo'
                .($entry->setting('tls', true) ? ', případně vypni šifrování (některé servery FTPS neumí).' : '.')
            );
        }

        // Za NATem server hlásí vnitřní adresu, správná je ta, na kterou jsme se připojili.
        @ftp_set_option($connection, FTP_USEPASVADDRESS, false);
        ftp_pasv($connection, true);

        $this->ftp = $connection;
        $this->root = (string) $entry->setting('root', '/');
    }

    public function list(string $path): array
    {
        $remote = VaultPath::join($this->root, $path);
        $items = @ftp_mlsd($this->ftp, $remote);

        if ($items === false) {
            return $this->rawList($remote);
        }

        return collect($items)
            ->filter(fn (array $item): bool => in_array($item['type'] ?? '', ['dir', 'file'], true))
            ->map(fn (array $item): array => [
                'name' => $item['name'],
                'type' => $item['type'],
                'size' => isset($item['size']) ? (int) $item['size'] : null,
                'modified' => isset($item['modify']) ? \DateTime::createFromFormat('YmdHis', substr($item['modify'], 0, 14))?->format('Y-m-d H:i') : null,
            ])
            ->sortBy(fn (array $item): string => ($item['type'] === 'dir' ? '0' : '1').$item['name'])
            ->values()
            ->all();
    }

    /** Server bez MLSD: unixový výpis `ls -l`. */
    private function rawList(string $remote): array
    {
        $lines = @ftp_rawlist($this->ftp, $remote);

        if ($lines === false) {
            throw new VaultException("Složka {$remote} na FTP neexistuje nebo ji nejde přečíst.");
        }

        $items = [];

        foreach ($lines as $line) {
            if (! preg_match('/^([dl-])\S*\s+\d+\s+\S+\s+\S+\s+(\d+)\s+(\w{3}\s+\d+\s+[\d:]+)\s+(.+)$/', $line, $m) || in_array($m[4], ['.', '..'], true)) {
                continue;
            }

            $items[] = [
                'name' => $m[1] === 'l' ? explode(' -> ', $m[4])[0] : $m[4],
                'type' => $m[1] === 'd' ? 'dir' : 'file',
                'size' => $m[1] === 'd' ? null : (int) $m[2],
                'modified' => $m[3],
            ];
        }

        return $items;
    }

    public function get(string $path): ?string
    {
        $remote = VaultPath::join($this->root, $path);

        if (@ftp_size($this->ftp, $remote) < 0) {
            return null;
        }

        $stream = fopen('php://temp', 'r+');

        if (! @ftp_fget($this->ftp, $stream, $remote, FTP_BINARY)) {
            fclose($stream);

            throw new VaultException("Soubor {$path} se z FTP nepodařilo stáhnout.");
        }

        rewind($stream);
        $contents = (string) stream_get_contents($stream);
        fclose($stream);

        return $contents;
    }

    public function put(string $path, string $contents): void
    {
        $remote = VaultPath::join($this->root, $path);
        $this->ensureDirectory(dirname($remote));

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);
        $ok = @ftp_fput($this->ftp, $remote, $stream, FTP_BINARY);
        fclose($stream);

        if (! $ok) {
            throw new VaultException("Soubor {$path} se na FTP nepodařilo nahrát (práva ke složce?).");
        }
    }

    public function delete(string $path): void
    {
        $remote = VaultPath::join($this->root, $path);

        if (@ftp_size($this->ftp, $remote) >= 0 && ! @ftp_delete($this->ftp, $remote)) {
            throw new VaultException("Soubor {$path} se na FTP nepodařilo smazat.");
        }
    }

    private function ensureDirectory(string $directory): void
    {
        $current = '';

        foreach (array_filter(explode('/', $directory)) as $part) {
            $current .= '/'.$part;

            if (! @ftp_chdir($this->ftp, $current)) {
                @ftp_mkdir($this->ftp, $current);
            }
        }
    }

    public function close(): void
    {
        @ftp_close($this->ftp);
    }
}
