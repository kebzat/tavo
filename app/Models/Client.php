<?php

namespace App\Models;

use App\Enums\Ads\PrimaryGoal;
use App\Models\Ads\AdAccount;
use App\Models\Ads\AdAlert;
use App\Models\Ads\AdClientSettings;
use App\Models\Ads\AdReport;
use App\Models\Crm\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Client extends Model
{
    protected $guarded = [];

    /** Firma v CRM, pro kterou jsme klienta založili kvůli auditu. */
    public function crmCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'crm_company_id');
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(Checklist::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(Audit::class);
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }

    public function adAccounts(): HasMany
    {
        return $this->hasMany(AdAccount::class);
    }

    /** Cíle, rozpočet a paušál u reklam. Založí se s prvním propojeným účtem. */
    public function adSettings(): HasOne
    {
        return $this->hasOne(AdClientSettings::class);
    }

    public function adAlerts(): HasMany
    {
        return $this->hasMany(AdAlert::class);
    }

    public function adReports(): HasMany
    {
        return $this->hasMany(AdReport::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /** Měsíční paušál po oblastech, viz App\Support\ClientDashboard. */
    public function retainers(): HasMany
    {
        return $this->hasMany(ClientRetainer::class)->orderBy('order_column')->orderBy('id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ClientTask::class);
    }

    public function months(): HasMany
    {
        return $this->hasMany(ClientMonth::class);
    }

    /** Odkaz na přehled spolupráce. Null, dokud ho nezapneme. */
    public function dashboardUrl(): ?string
    {
        return $this->dashboard_enabled ? $this->dashboardPreviewUrl() : null;
    }

    /**
     * Náhled pro přihlášeného správce, funguje i u vypnutého přehledu.
     *
     * Přehled ukazuje peníze a hodiny, proto náhodný token místo čitelné
     * adresy, jakou mají audity. Vzniká až tady: starší datové migrace
     * zakládají klienty dřív, než sloupec existuje, hook při zakládání
     * by je na čisté databázi shodil.
     */
    public function dashboardPreviewUrl(): string
    {
        if (! $this->dashboard_token) {
            $this->forceFill(['dashboard_token' => Str::random(40)])->saveQuietly();
        }

        return route('client-dashboard.show', $this->dashboard_token);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }

    /** Klienti, kterým hlídáme reklamy: aspoň jeden zapnutý reklamní účet. */
    public function scopeWithAds(Builder $query): Builder
    {
        return $query->whereHas('adAccounts', fn (Builder $query) => $query->where('is_active', true)->ads());
    }

    /**
     * Zapnuté reklamní účty, bez analytiky. Z nich se sčítá útrata a konverze.
     *
     * @return list<int>
     */
    public function activeAdAccountIds(): array
    {
        return $this->adAccounts
            ->filter(fn (AdAccount $account): bool => $account->is_active && ! $account->isAnalytics())
            ->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
    }

    /**
     * Zapnuté GA4 property.
     *
     * @return list<int>
     */
    public function activeAnalyticsAccountIds(): array
    {
        return $this->adAccounts
            ->filter(fn (AdAccount $account): bool => $account->is_active && $account->isAnalytics())
            ->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
    }

    public function adGoal(): PrimaryGoal
    {
        return $this->adSettings?->primary_goal ?? PrimaryGoal::Purchases;
    }

    /** Měna prvního aktivního účtu. Klienti s účty v různých měnách jsou výjimka. */
    public function adCurrency(): string
    {
        return $this->adAccounts->first(fn (AdAccount $account): bool => $account->is_active && ! $account->isAnalytics())?->currency ?? 'CZK';
    }

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
            'dashboard_enabled' => 'boolean',
            'started_on' => 'date',
        ];
    }
}
