<?php

namespace App\Models;

use App\Enums\Ads\PrimaryGoal;
use App\Models\Ads\AdAccount;
use App\Models\Ads\AdAlert;
use App\Models\Ads\AdBusinessResult;
use App\Models\Ads\AdCampaignGroup;
use App\Models\Ads\AdChange;
use App\Models\Ads\AdClientSettings;
use App\Models\Ads\AdEvent;
use App\Models\Ads\AdReport;
use App\Models\Crm\Company;
use App\Models\Marketing\ClientBriefing;
use App\Models\Marketing\CreativeTest;
use App\Models\Marketing\LandingCheck;
use App\Models\Marketing\LeadFeedback;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Client extends Model
{
    /**
     * Karta klienta („mozek“). Klíč v JSON sloupci `profile` => popisek.
     * Čte ji agent i správce v nástrojích, viz docs/MARKETING.md.
     */
    public const PROFILE_FIELDS = [
        'cooperation' => 'Typ spolupráce',
        'goals' => 'Cíle',
        'kpis' => 'KPI a jak je měříme',
        'scope' => 'Rozsah spolupráce',
        'budget' => 'Rozpočet',
        'source_of_truth' => 'Zdroj pravdy pro čísla',
        'constraints' => 'Omezení a co nesmíme',
        'approvals' => 'Schvalování',
        'brand_voice' => 'Tón komunikace a zakázaná slova',
        'season' => 'Sezona, marže a priority',
        'notes' => 'Další poznámky',
    ];

    /** Víc cílových stránek denně nekontrolujeme, ať weby klientů zbytečně nezatěžujeme. */
    public const MAX_LANDING_PAGES = 5;

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

    /** Skupiny kampaní („Prodej“, „Brand“, „Nábor“), viz docs/ADS.md. */
    public function adCampaignGroups(): HasMany
    {
        return $this->hasMany(AdCampaignGroup::class)->orderBy('sort')->orderBy('name');
    }

    /** Kalendář termínů: konce licencí, akce, čekání na podklady. */
    public function adEvents(): HasMany
    {
        return $this->hasMany(AdEvent::class);
    }

    /** Skutečné objednávky a tržby e-shopu, zapsané ručně. */
    public function adBusinessResults(): HasMany
    {
        return $this->hasMany(AdBusinessResult::class)->latest('period_to');
    }

    /** Historie zásahů: změny rozpočtu, kreativ, nabídky, webu. */
    public function adChanges(): HasMany
    {
        return $this->hasMany(AdChange::class);
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

    /**
     * Schválené úkoly. Návrhy od agenta sem nepatří, dokud je člověk
     * neschválí: jinak by se ukázaly klientovi na přehledu spolupráce.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(ClientTask::class)->where('is_proposal', false);
    }

    /** Návrhy úkolů od agenta (ze schůzek, e-mailů), čekají na schválení. */
    public function taskProposals(): HasMany
    {
        return $this->hasMany(ClientTask::class)->where('is_proposal', true);
    }

    public function months(): HasMany
    {
        return $this->hasMany(ClientMonth::class);
    }

    /** Vyfakturované měsíce, viz App\Support\Ads\Billing. */
    public function invoices(): HasMany
    {
        return $this->hasMany(ClientInvoice::class);
    }

    /** Archiv klienta: podklady mozku s tímhle klientem (docs/MOZEK.md). */
    public function knowledgeSources(): HasMany
    {
        return $this->hasMany(KnowledgeSource::class);
    }

    /** Poznatky mozku o klientovi. */
    public function knowledgeEntries(): HasMany
    {
        return $this->hasMany(KnowledgeEntry::class);
    }

    /** Ranní přehledy od agenta, nejnovější první. */
    public function briefings(): HasMany
    {
        return $this->hasMany(ClientBriefing::class)->orderByDesc('date');
    }

    /** Zpětná vazba ke kvalitě leadů od klienta. */
    public function leadFeedback(): HasMany
    {
        return $this->hasMany(LeadFeedback::class)->orderByDesc('period_to');
    }

    public function creativeTests(): HasMany
    {
        return $this->hasMany(CreativeTest::class)->orderByDesc('started_on');
    }

    public function landingChecks(): HasMany
    {
        return $this->hasMany(LandingCheck::class);
    }

    /**
     * Pole karty klienta (cíle, KPI, rozsah…), prázdná vynechaná.
     *
     * @return array<string, string>
     */
    public function profileFields(): array
    {
        return collect(self::PROFILE_FIELDS)
            ->mapWithKeys(fn (string $label, string $key): array => [$label => trim((string) ($this->profile[$key] ?? ''))])
            ->filter()
            ->all();
    }

    /** Cílové stránky ke kontrole: vyplněné v kartě, jinak web klienta. */
    public function landingUrls(): array
    {
        $urls = collect($this->landing_pages ?? [])->pluck('url')->filter();

        if ($urls->isEmpty() && filled($this->website_url)) {
            $urls = collect([$this->website_url]);
        }

        return $urls->map(fn ($url): string => trim((string) $url))
            ->filter(fn (string $url): bool => (bool) preg_match('~^https?://~i', $url))
            ->unique()
            ->take(self::MAX_LANDING_PAGES)
            ->values()
            ->all();
    }

    /** Odkaz na přehled spolupráce. Null, dokud ho nezapneme. */
    public function dashboardUrl(): ?string
    {
        return $this->dashboard_enabled ? $this->dashboardPreviewUrl() : null;
    }

    /**
     * Náhled pro přihlášeného správce, funguje i u vypnutého přehledu.
     *
     * Adresa je název klienta a šest náhodných znaků (/klient/svet-cejlonu-k7f2q9).
     * Přehled ukazuje peníze a hodiny, náhodná část ho chrání před uhodnutím.
     * Vzniká až tady: starší datové migrace zakládají klienty dřív, než sloupec
     * existuje, hook při zakládání by je na čisté databázi shodil. Po přejmenování
     * klienta zůstává stejná, ať odkaz, který už klient má, funguje dál.
     */
    public function dashboardPreviewUrl(array $query = []): string
    {
        return route('client-dashboard.show', [$this->dashboardKey()] + $query);
    }

    public function dashboardKey(): string
    {
        // Starší datové migrace (ukázkový klient) volají adresu dřív, než
        // sloupec dashboard_slug vznikne. Do té doby platí jen token.
        static $hasSlug = false;
        $hasSlug = $hasSlug || Schema::hasColumn('clients', 'dashboard_slug');

        if (! $hasSlug) {
            if (! $this->dashboard_token) {
                $this->forceFill(['dashboard_token' => Str::random(40)])->saveQuietly();
            }

            return $this->dashboard_token;
        }

        if (! $this->dashboard_slug) {
            $this->forceFill([
                'dashboard_slug' => Str::limit(Str::slug($this->name), 60, '').'-'.Str::lower(Str::random(6)),
            ])->saveQuietly();
        }

        return $this->dashboard_slug;
    }

    /** Najde klienta podle adresy přehledu, i podle starého 40znakového tokenu. */
    public function scopeDashboardAt(Builder $query, string $key): Builder
    {
        return $query->where(fn (Builder $query) => $query->where('dashboard_slug', $key)->orWhere('dashboard_token', $key));
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
            'dashboard_shows_pricing' => 'boolean',
            'started_on' => 'date',
            'profile' => 'array',
            'contacts' => 'array',
            'decisions' => 'array',
            'landing_pages' => 'array',
            'billing_statement' => 'array',
        ];
    }
}
