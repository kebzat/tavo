<?php

namespace App\Models;

use App\Enums\TaskStatus;
use App\Enums\WorkArea;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Úkol u klienta. Klient na přehledu vidí název, popis, stav a odpracované
 * hodiny, ne jednotlivé zápisy času ani interní poznámku.
 *
 * Popis se píše v editoru a ukládá jako HTML. Starší úkoly mají prostý text,
 * ten se při čtení převede (entery, odkazy), v databázi zůstane, dokud úkol
 * někdo neuloží znovu.
 */
class ClientTask extends Model
{
    protected $guarded = [];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class, 'task_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', '!=', TaskStatus::Done->value);
    }

    protected static function booted(): void
    {
        static::saving(function (self $task): void {
            // Datum dokončení se doplní samo a při návratu do práce zmizí.
            if ($task->status === TaskStatus::Done) {
                $task->done_on ??= now()->toDateString();
            } else {
                $task->done_on = null;
            }

            $task->planned_for = $task->planned_for?->startOfMonth();
        });
    }

    protected function casts(): array
    {
        return [
            'area' => WorkArea::class,
            'status' => TaskStatus::class,
            'planned_for' => 'date',
            'done_on' => 'date',
        ];
    }

    /** Popis pro přehled klienta: HTML bez nebezpečných značek, odkazy do nové karty. */
    public function descriptionHtml(): ?HtmlString
    {
        if (blank(strip_tags((string) $this->description))) {
            return null;
        }

        $html = Str::sanitizeHtml($this->description);
        $html = preg_replace('/<a (?![^>]*\btarget=)/i', '<a target="_blank" rel="noopener" ', $html);

        return new HtmlString($html);
    }

    /** Starší prostý text jako HTML, ať ho editor i přehled zobrazí se zalomením. */
    protected function description(): Attribute
    {
        return Attribute::get(fn (?string $value): ?string => self::isHtml($value) ? $value : self::plainToHtml($value));
    }

    private static function isHtml(?string $value): bool
    {
        return $value !== null && preg_match('~<(p|br|ul|ol|li|strong|b|em|i|u|s|a|h[1-6])[\s>/]~i', $value) === 1;
    }

    private static function plainToHtml(?string $value): ?string
    {
        if (blank($value)) {
            return $value;
        }

        return collect(preg_split('/\R\s*\R/', trim($value)))
            ->map(fn (string $paragraph): string => '<p>'.preg_replace('/\R/', '<br>', self::linkify(e(trim($paragraph)))).'</p>')
            ->implode('');
    }

    /** Holé adresy v textu jako odkazy. Tečka nebo závorka na konci k adrese nepatří. */
    private static function linkify(string $escaped): string
    {
        return preg_replace_callback('~https?://[^\s<]+~', function (array $match): string {
            $url = rtrim($match[0], '.,;:!?)');

            return '<a href="'.$url.'">'.$url.'</a>'.substr($match[0], strlen($url));
        }, $escaped);
    }
}
