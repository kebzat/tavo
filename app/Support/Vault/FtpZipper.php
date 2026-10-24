<?php

namespace App\Support\Vault;

use ZipStream\ZipStream;

/**
 * Stáhne složku z FTP jako ZIP a posílá ho průběžně, ať spojení nevyprší
 * ani u celého webu. Nahrané obrázky, cache a zálohy vynechá (agent je na
 * kontrolu kódu nepotřebuje), stejně jako soubory nad 10 MB. Co vynechal,
 * napíše do `_vynechano.txt` v archivu.
 */
class FtpZipper
{
    public const DEFAULT_EXCLUDES = [
        '.git', 'node_modules',
        'wp-content/uploads', 'wp-content/cache', 'wp-content/upgrade', 'wp-content/backups',
        'wp-content/backup-db', 'wp-content/updraft', 'wp-content/ai1wm-backups', 'wp-content/wflogs',
    ];

    public const MAX_FILE_BYTES = 10 * 1024 * 1024;

    public const MAX_TOTAL_BYTES = 400 * 1024 * 1024;

    public const MAX_FILES = 30000;

    private int $files = 0;

    private int $bytes = 0;

    /** @var list<string> */
    private array $skipped = [];

    /** @param  list<string>  $excludes  relativní cesty od složky webu */
    public function __construct(private FtpClient $ftp, private array $excludes = self::DEFAULT_EXCLUDES) {}

    /** @param  resource  $output */
    public function write(string $path, $output): array
    {
        $path = VaultPath::normalize($path);
        $zip = new ZipStream(outputStream: $output, sendHttpHeaders: false, flushOutput: true, defaultEnableZeroHeader: true);

        $this->walk($zip, $path, $path === '' ? '' : basename($path));

        if ($this->skipped !== []) {
            $zip->addFile('_vynechano.txt', implode("\n", $this->skipped)."\n");
        }

        $zip->finish();

        return ['soubory' => $this->files, 'bajty' => $this->bytes, 'vynechano' => count($this->skipped)];
    }

    private function walk(ZipStream $zip, string $path, string $inZip): void
    {
        foreach ($this->ftp->list($path) as $item) {
            $child = ltrim($path.'/'.$item['name'], '/');
            $childInZip = ltrim($inZip.'/'.$item['name'], '/');

            if ($this->excluded($child)) {
                $this->skipped[] = $child.($item['type'] === 'dir' ? '/' : '').' (vynecháno)';

                continue;
            }

            if ($item['type'] === 'dir') {
                $this->walk($zip, $child, $childInZip);

                continue;
            }

            if (($item['size'] ?? 0) > self::MAX_FILE_BYTES) {
                $this->skipped[] = $child.' (víc než 10 MB)';

                continue;
            }

            if ($this->files >= self::MAX_FILES || $this->bytes >= self::MAX_TOTAL_BYTES) {
                $this->skipped[] = $child.' (archiv je plný, stáhni menší složku)';

                continue;
            }

            $contents = $this->ftp->get($child);

            if ($contents === null) {
                continue;
            }

            $zip->addFile($childInZip, $contents);
            $this->files++;
            $this->bytes += strlen($contents);
        }
    }

    private function excluded(string $path): bool
    {
        foreach ($this->excludes as $exclude) {
            $exclude = trim($exclude, '/');

            if ($exclude !== '' && ($path === $exclude || str_starts_with($path, $exclude.'/'))) {
                return true;
            }
        }

        return false;
    }
}
