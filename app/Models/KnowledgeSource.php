<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Podklady pro mozek: vložený text nebo soubory (poznámky, e-maily, přepisy
 * schůzek), ze kterých agent na pozadí udělá návrhy poznatků. Průběh:
 * queued → running → done / failed, bez učení rovnou stored.
 *
 * Podklady s klientem jsou zároveň jeho archiv (data, strategie, výsledky,
 * marže): zůstávají uložené a agent v projektu klienta je najde a přečte
 * celé, i když se z nich do mozku dostalo jen pár poznatků. Viz docs/MOZEK.md.
 */
class KnowledgeSource extends Model
{
    protected $guarded = [];

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    /** Jen uložené do archivu, agent z nich poznatky nedělal. */
    public const STATUS_STORED = 'stored';

    /** @return array<string, string> druh dokumentu v archivu klienta */
    public static function categories(): array
    {
        return [
            'strategie' => 'Vize a strategie',
            'data' => 'Data a analýzy',
            'vysledky' => 'Výsledky a reporty',
            'obchod' => 'Obchodní data a marže',
            'schuzky' => 'Schůzky a domluvy',
            'jine' => 'Jiné',
        ];
    }

    protected static function booted(): void
    {
        // Archiv může obsahovat marže a obchodní čísla: se záznamem zmizí i soubory.
        static::deleted(function (self $source): void {
            Storage::disk('local')->delete(collect($source->files ?? [])->pluck('path')->filter()->all());
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Skill, ke kterému poznatek / dokument patří (know-how skillu, zkušenost u klienta v jeho oboru). */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
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

    public function isStored(): bool
    {
        return $this->status === self::STATUS_STORED;
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

    public function categoryLabel(): ?string
    {
        return $this->category ? (self::categories()[$this->category] ?? $this->category) : null;
    }

    /**
     * HTML stránka v dokumentu (rozbor, plán od agenta): první soubor .html.
     * Dokument načtený odjinud (ClickUp) stránku nemá.
     *
     * @return array{path: string, name?: string}|null
     */
    public function pageFile(): ?array
    {
        // Přílohy z ClickUpu nejsou rozbor ke čtení, stránka bez přihlášení by je zveřejnila.
        if (filled($this->external_ref)) {
            return null;
        }

        return collect($this->files ?? [])->first(fn (array $file): bool => (bool) preg_match('/\.html?$/i', $file['name'] ?? $file['path'] ?? ''));
    }

    /**
     * Stálá adresa stránky ke čtení, i bez přihlášení. Čitelný název a náhodná
     * část jako u přehledu klienta (/stranka/snowbear-google-ads-k7f2q9xm).
     */
    public function pageUrl(): ?string
    {
        if ($this->pageFile() === null) {
            return null;
        }

        if (! $this->page_key) {
            $name = rtrim(Str::limit(Str::slug(str_replace('/', ' ', trim($this->client?->name.' '.$this->title))), 60, ''), '-') ?: 'stranka';
            $this->forceFill(['page_key' => $name.'-'.Str::lower(Str::random(8))])->saveQuietly();
        }

        return route('archiv.stranka', $this->page_key);
    }

    /** Tvar pro agenta: co v archivu je a jak to stáhnout. */
    public function toAgent(): array
    {
        return array_filter([
            'id' => $this->getKey(),
            'nazev' => $this->title,
            'druh' => $this->categoryLabel(),
            'skill' => $this->skill?->slug,
            'k_datu' => $this->document_date?->toDateString(),
            'popis' => $this->summary,
            'poznamka' => $this->note,
            'text' => filled($this->body) ? route('agent.api.mozek.zdroj', $this) : null,
            'soubory' => collect($this->files ?? [])->values()->map(fn (array $file, int $index): array => [
                'jmeno' => $file['name'] ?? basename($file['path'] ?? ''),
                'adresa' => route('agent.api.mozek.soubor', [$this, $index]),
            ])->all() ?: null,
            'stranka' => $this->pageUrl(),
            'nahral' => $this->author ? $this->author.' '.$this->created_at?->toDateString() : $this->created_at?->toDateString(),
        ], fn ($value): bool => $value !== null);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_STORED => 'Uloženo',
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
            self::STATUS_STORED => 'gray',
            default => 'warning',
        };
    }

    protected function casts(): array
    {
        return [
            'files' => 'array',
            'document_date' => 'date',
            'learn' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
