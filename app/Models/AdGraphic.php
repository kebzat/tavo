<?php

namespace App\Models;

use App\Support\AdGraphics\Formats;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Zadání reklamní grafiky pro agenta. Průběh: queued → running → done / failed.
 * Hotové návrhy (PNG) leží v kontejneru agenta v předání projektu
 * (predani/grafika-{id}), na webu jsou jen jejich jména v `outputs`.
 */
class AdGraphic extends Model
{
    protected $guarded = [];

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Úpravy hotových návrhů, od nejstarší. */
    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->oldest('id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function agentJob(): BelongsTo
    {
        return $this->belongsTo(AgentJob::class);
    }

    /** První zadání v řadě úprav (úpravy mají parent_id vždy na něj). */
    public function root(): self
    {
        return $this->parent ?? $this;
    }

    /**
     * Celá řada: první zadání a jeho úpravy po sobě.
     *
     * @return Collection<int, self>
     */
    public function rounds(): Collection
    {
        $root = $this->root();

        return collect([$root])->concat($root->revisions()->with('agentJob')->get());
    }

    /** Předchozí kolo v řadě (u prvního zadání null). */
    public function previousRound(): ?self
    {
        return $this->parent_id === null ? null : $this->rounds()->filter(fn (self $round): bool => $round->getKey() < $this->getKey())->last();
    }

    /** Složka v předání projektu, kam agent ukládá návrhy. */
    public function folder(): string
    {
        return 'grafika-'.$this->getKey();
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isRunning(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_RUNNING], true);
    }

    /** Nový jednorázový přístup pro agenta. Uloží se jen otisk. */
    public function issueToken(): string
    {
        $token = Str::random(48);
        $this->forceFill(['token_hash' => hash('sha256', $token)])->save();

        return $token;
    }

    public function tokenMatches(?string $token): bool
    {
        return filled($token) && filled($this->token_hash) && hash_equals($this->token_hash, hash('sha256', $token));
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_QUEUED => 'Čeká',
            self::STATUS_RUNNING => 'Agent pracuje',
            self::STATUS_DONE => 'Hotovo',
            default => 'Nepovedlo se',
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

    /** Jen rozměry, do úzkých míst (seznam). */
    public function formatsShort(): string
    {
        return collect($this->formats ?? [])->map(fn (array $format): string => $format['width'].'×'.$format['height'])->implode(', ');
    }

    public function formatsLabel(): string
    {
        return collect($this->formats ?? [])->map(fn (array $format): string => Formats::label($format))->implode(', ');
    }

    protected function casts(): array
    {
        return [
            'formats' => 'array',
            'colors' => 'array',
            'assets' => 'array',
            'outputs' => 'array',
            'every_format' => 'boolean',
            'ai_images' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
