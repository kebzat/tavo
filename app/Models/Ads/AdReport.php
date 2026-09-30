<?php

namespace App\Models\Ads;

use App\Enums\Ads\ReportStatus;
use App\Enums\Ads\ReportType;
use App\Models\Client;
use App\Models\Concerns\TracksClientViews;
use App\Support\UniqueSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Týdenní nebo měsíční přehled reklam pro klienta.
 *
 * Čísla se při vytvoření zmrazí do `snapshot`. Meta konverze zpětně
 * dopočítává, a kdyby se report četl z živých dat, klient by za měsíc
 * v tomtéž odkazu viděl jiná čísla, než o kterých jsme spolu mluvili.
 * Přepočítat jde ručně akcí „Obnovit čísla“.
 */
class AdReport extends Model
{
    use TracksClientViews;

    protected $guarded = [];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /** Najde report podle čitelné adresy nebo náhodného tokenu. */
    public function scopeSharedAs(Builder $query, string $key): Builder
    {
        return $query->where(fn (Builder $query) => $query->where('slug', $key)->orWhere('public_token', $key));
    }

    /** Odkaz pro klienta. Null, dokud sdílení nezapneme. */
    public function publicUrl(): ?string
    {
        return $this->is_public ? $this->previewUrl() : null;
    }

    /** Náhled pro přihlášeného správce, funguje i u konceptu. */
    public function previewUrl(): string
    {
        return route('ad-report.show', $this->slug ?: $this->public_token);
    }

    protected function viewActivitySubject(bool $first): string
    {
        return $first ? 'Otevřeli report reklam poprvé' : 'Znovu otevřeli report reklam';
    }

    protected static function booted(): void
    {
        static::saving(function (self $report): void {
            if (! $report->public_token) {
                $report->public_token = Str::random(40);
            }

            // /report/svet-caje-2026-09-22: klient, začátek období.
            if (! $report->slug) {
                $report->slug = UniqueSlug::for(
                    $report,
                    ($report->client?->name ?? 'report').' '.$report->period_start?->format('Y-m-d'),
                    'report',
                );
            }
        });
    }

    protected function casts(): array
    {
        return [
            'type' => ReportType::class,
            'status' => ReportStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'snapshot' => 'array',
            'sent_to' => 'array',
            'is_public' => 'boolean',
            'sent_at' => 'datetime',
            'first_viewed_at' => 'datetime',
            'last_viewed_at' => 'datetime',
        ];
    }
}
