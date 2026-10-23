<?php

namespace App\Models\Ads;

use App\Enums\Ads\AlertSeverity;
use App\Enums\Ads\AlertStatus;
use App\Models\Client;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Upozornění z denní kontroly: co se změnilo proti čemu, z jakého množství
 * dat a co prověřit. Opakovaný problém je jedno upozornění (počítá výskyty),
 * vrácení do normálu se zapíše do deníku.
 */
class AdAlert extends Model
{
    /** Kategorie: problém s daty (stahování, měření, stránky), nebo výkon proti cíli. */
    public const DATA = 'data';

    public const PERFORMANCE = 'performance';

    protected $guarded = [];

    protected $attributes = [
        'category' => self::PERFORMANCE,
        'occurrences' => 1,
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AdAccount::class, 'ad_account_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(AdCampaignGroup::class, 'ad_campaign_group_id');
    }

    /** Nové, řešené a čekající na klienta, i odložená. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->whereIn('status', AlertStatus::active());
    }

    /** Živá a neodložená: to, co má dnes svítit. */
    public function scopeVisible(Builder $query, CarbonInterface|string|null $today = null): Builder
    {
        $today = CarbonImmutable::parse($today ?? today())->toDateString();

        return $query->live()->where(fn (Builder $query) => $query->whereNull('snoozed_until')->orWhereDate('snoozed_until', '<=', $today));
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', AlertStatus::Open)->where(fn (Builder $query) => $query->whereNull('snoozed_until')->orWhereDate('snoozed_until', '<=', today()->toDateString()));
    }

    public function scopeWorstFirst(Builder $query): Builder
    {
        return $query
            ->orderByRaw("case severity when 'critical' then 0 when 'warning' then 1 else 2 end")
            ->latest('last_seen_on');
    }

    public function isSnoozed(CarbonInterface|string|null $today = null): bool
    {
        return $this->snoozed_until !== null && $this->snoozed_until->gt(CarbonImmutable::parse($today ?? today()));
    }

    /** Krátký zápis do deníku upozornění: „24. 10.: Řeším (Pavel)“. */
    public function log(string $text, ?CarbonInterface $at = null): void
    {
        $history = $this->history ?? [];
        $history[] = CarbonImmutable::parse($at ?? now())->format('j. n.').': '.$text;
        // Deník je pro orientaci, stačí posledních pár událostí.
        $this->history = array_slice($history, -12);
    }

    /** Změna stavu z rozhraní, s deníkem. */
    public function moveTo(AlertStatus $status, ?string $who = null, ?string $waitingFor = null): void
    {
        $this->status = $status;
        $this->resolved_at = $status === AlertStatus::Resolved ? now() : null;
        $this->resolved_by = $status === AlertStatus::Resolved ? 'user' : null;
        $this->waiting_for = $status === AlertStatus::Waiting ? $waitingFor : null;
        $this->log($status->getLabel().($waitingFor && $status === AlertStatus::Waiting ? ': '.$waitingFor : '').($who ? ' ('.$who.')' : ''));
        $this->save();
    }

    public function snooze(CarbonInterface $until, ?string $who = null): void
    {
        $this->snoozed_until = $until;
        $this->log('Odloženo do '.$until->format('j. n.').($who ? ' ('.$who.')' : ''));
        $this->save();
    }

    /** Text pro řádek: čísla, ze kterých pravidlo vycházelo. */
    public function detail(): string
    {
        return collect([$this->facts, $this->recommendation])->filter()->implode("\n\n");
    }

    protected function casts(): array
    {
        return [
            'severity' => AlertSeverity::class,
            'status' => AlertStatus::class,
            'snapshot' => 'array',
            'history' => 'array',
            'detected_on' => 'date',
            'last_seen_on' => 'date',
            'snoozed_until' => 'date',
            'recovered_on' => 'date',
            'resolved_at' => 'datetime',
            'occurrences' => 'integer',
        ];
    }
}
