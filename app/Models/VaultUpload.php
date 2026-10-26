<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Soubory, které agent chce nahrát přes FTP z trezoru. Podle nastavení
 * přístupu čekají na kliknutí člověka, nebo se nahrají hned. Původní verze
 * se před nahráním uloží, takže jde nahrání vrátit (App\Support\Vault\FtpUploader).
 *
 * `files`: [{path, size, existed}], obsah leží v storage/app/private/trezor/{id}/.
 */
class VaultUpload extends Model
{
    protected $guarded = [];

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_DISCARDED = 'discarded';

    public const STATUS_REVERTED = 'reverted';

    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'files' => 'array',
            'applied_at' => 'datetime',
            'reverted_at' => 'datetime',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(VaultEntry::class, 'vault_entry_id');
    }

    /** @return array<string, string> */
    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING => 'Čeká na schválení',
            self::STATUS_APPLIED => 'Nahráno na web',
            self::STATUS_DISCARDED => 'Zahozeno',
            self::STATUS_REVERTED => 'Vráceno',
            self::STATUS_FAILED => 'Nepovedlo se',
        ];
    }

    public function statusLabel(): string
    {
        return self::statuses()[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_APPLIED => 'success',
            self::STATUS_FAILED => 'danger',
            default => 'gray',
        };
    }

    public function directory(): string
    {
        return 'trezor/'.$this->getKey();
    }

    /** Soubory jako text pro přehled: „nový“ / „změněný“. */
    public function filesSummary(): string
    {
        return collect($this->files)
            ->map(fn (array $file): string => $file['path'].($file['existed'] === false ? ' (nový)' : ''))
            ->implode("\n");
    }
}
