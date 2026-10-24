<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Podklady pro mozek: vložený text nebo soubory (poznámky, e-maily, přepisy
 * schůzek), ze kterých agent na pozadí udělá návrhy poznatků. Průběh:
 * queued → running → done / failed. Viz docs/MOZEK.md.
 */
class KnowledgeSource extends Model
{
    protected $guarded = [];

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function agentJob(): BelongsTo
    {
        return $this->belongsTo(AgentJob::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(KnowledgeEntry::class);
    }

    public function isRunning(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_RUNNING], true);
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /** Velikost podkladů v lidské podobě (znaky textu + počet souborů). */
    public function sizeLabel(): string
    {
        $chars = mb_strlen((string) $this->body);

        return collect([
            $chars > 0 ? number_format($chars, 0, ',', ' ').' znaků' : null,
            count($this->files ?? []) > 0 ? count($this->files).' '.(count($this->files) === 1 ? 'soubor' : (count($this->files) < 5 ? 'soubory' : 'souborů')) : null,
        ])->filter()->implode(' + ') ?: 'prázdné';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_QUEUED => 'Čeká',
            self::STATUS_RUNNING => 'Agent čte',
            self::STATUS_DONE => 'Hotovo',
            self::STATUS_FAILED => 'Nepovedlo se',
            default => $this->status,
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_DONE => 'success',
            self::STATUS_FAILED => 'danger',
            default => 'warning',
        };
    }

    protected function casts(): array
    {
        return [
            'files' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
