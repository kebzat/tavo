<?php

namespace App\Support\Vault;

use App\Models\VaultEntry;
use App\Models\VaultUpload;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Nahrávání souborů od agenta přes FTP z trezoru. Agent soubory jen předá,
 * podle přístupu se nahrají hned, nebo počkají na kliknutí v nástrojích.
 * Před nahráním se uloží, co bylo na webu, takže jde všechno vrátit.
 * Mazat soubory na webu agent nemůže (jen vrácení smaže soubory, které přidal).
 */
class FtpUploader
{
    public const MAX_FILES = 200;

    public const MAX_BYTES = 30 * 1024 * 1024;

    public function __construct(private FtpConnector $connector) {}

    /** @param  array<string, string>  $files  relativní cesta => obsah */
    public function request(VaultEntry $entry, array $files, ?string $description, string $actor): VaultUpload
    {
        if (! $entry->agentAllows('approve')) {
            throw new VaultException('U tohoto přístupu agent nahrávat nesmí. Změnit to jde v trezoru („Co smí agent“).');
        }

        if ($files === [] || count($files) > self::MAX_FILES) {
            throw new VaultException('Najednou jde nahrát 1 až '.self::MAX_FILES.' souborů.');
        }

        if (array_sum(array_map('strlen', $files)) > self::MAX_BYTES) {
            throw new VaultException('Najednou jde nahrát nejvýš 30 MB.');
        }

        $normalized = [];

        foreach ($files as $path => $contents) {
            $path = VaultPath::normalize((string) $path);

            if ($path === '') {
                throw new VaultException('Chybí cesta souboru.');
            }

            $normalized[$path] = $contents;
        }

        $upload = $entry->uploads()->create([
            'description' => $description,
            'files' => array_map(fn (string $path): array => ['path' => $path, 'size' => strlen($normalized[$path]), 'existed' => null], array_keys($normalized)),
            'status' => VaultUpload::STATUS_PENDING,
        ]);

        foreach ($normalized as $path => $contents) {
            Storage::disk('local')->put($upload->directory().'/nove/'.$path, $contents);
        }

        $entry->record($actor, 'upload_requested', $this->summary($upload));

        return $entry->agentAllows('write') ? $this->apply($upload, $actor) : $upload;
    }

    /** Nahraje na web. Co bylo na webu předtím, uloží vedle (puvodni/). */
    public function apply(VaultUpload $upload, string $actor): VaultUpload
    {
        if ($upload->status !== VaultUpload::STATUS_PENDING) {
            throw new VaultException('Tohle nahrání už je vyřízené.');
        }

        $entry = $upload->entry;
        $disk = Storage::disk('local');
        $files = $upload->files;
        $done = 0;

        try {
            $ftp = $this->connector->open($entry);

            try {
                foreach ($files as $i => $file) {
                    $original = $ftp->get($file['path']);
                    $files[$i]['existed'] = $original !== null;

                    if ($original !== null) {
                        $disk->put($upload->directory().'/puvodni/'.$file['path'], $original);
                    }

                    $ftp->put($file['path'], (string) $disk->get($upload->directory().'/nove/'.$file['path']));
                    $done++;
                }
            } finally {
                $ftp->close();
            }
        } catch (Throwable $e) {
            $message = $e instanceof VaultException ? $e->getMessage() : 'FTP: '.$e->getMessage();
            $upload->update([
                'files' => $files,
                'status' => $done > 0 ? VaultUpload::STATUS_APPLIED : VaultUpload::STATUS_FAILED,
                'applied_at' => $done > 0 ? now() : null,
                'error' => $message.($done > 0 ? " Nahráno {$done} z ".count($files).' souborů, vrátit je jde tlačítkem Vrátit.' : ''),
                'decided_by' => $actor,
            ]);
            $entry->record($actor, 'upload_failed', $upload->error);

            throw new VaultException($upload->error);
        }

        $upload->update([
            'files' => $files,
            'status' => VaultUpload::STATUS_APPLIED,
            'applied_at' => now(),
            'error' => null,
            'decided_by' => $actor,
        ]);
        $entry->record($actor, 'upload_applied', $this->summary($upload));

        return $upload;
    }

    public function discard(VaultUpload $upload, string $actor): void
    {
        if ($upload->status !== VaultUpload::STATUS_PENDING) {
            throw new VaultException('Tohle nahrání už je vyřízené.');
        }

        $upload->update(['status' => VaultUpload::STATUS_DISCARDED, 'decided_by' => $actor]);
        Storage::disk('local')->deleteDirectory($upload->directory());
        $upload->entry->record($actor, 'upload_discarded', $this->summary($upload));
    }

    /** Vrátí na web původní soubory, nově přidané smaže. */
    public function revert(VaultUpload $upload, string $actor): void
    {
        if ($upload->status !== VaultUpload::STATUS_APPLIED) {
            throw new VaultException('Vrátit jde jen nahrání, které je na webu.');
        }

        $disk = Storage::disk('local');
        $ftp = $this->connector->open($upload->entry);

        try {
            foreach ($upload->files as $file) {
                match ($file['existed']) {
                    true => $ftp->put($file['path'], (string) $disk->get($upload->directory().'/puvodni/'.$file['path'])),
                    false => $ftp->delete($file['path']),
                    // Na tenhle soubor nahrání nedošlo (selhalo dřív).
                    default => null,
                };
            }
        } finally {
            $ftp->close();
        }

        $upload->update(['status' => VaultUpload::STATUS_REVERTED, 'reverted_at' => now(), 'decided_by' => $actor]);
        $upload->entry->record($actor, 'upload_reverted', $this->summary($upload));
    }

    private function summary(VaultUpload $upload): string
    {
        return '#'.$upload->getKey().': '.collect($upload->files)->pluck('path')->implode(', ')
            .($upload->description ? ' ('.$upload->description.')' : '');
    }
}
