<?php

namespace App\Models\Crm;

use App\Enums\Crm\ActivityType;
use App\Enums\Crm\CompanySegment;
use App\Enums\Crm\CompanySource;
use App\Enums\Crm\CompanyStatus;
use App\Enums\Crm\Priority;
use App\Models\User;
use App\Support\Crm\Domain;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

/**
 * Firma, kterou oslovujeme. Střed celého CRM — kontakty, obchody
 * i aktivity visí na ní.
 */
class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'crm_companies';

    protected $guarded = [];

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /** Kontakt, na který voláme a píšeme jako první. */
    public function primaryContact(): HasMany
    {
        return $this->contacts()->where('is_primary', true);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function demands(): HasMany
    {
        return $this->hasMany(Demand::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'crm_company_tag', 'company_id', 'tag_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Dotazy pro přehled „Dnes"
    |--------------------------------------------------------------------------
    */

    /** Follow-up měl proběhnout a neproběhl. */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotNull('next_action_at')
            ->where('next_action_at', '<', now()->startOfDay())
            ->whereNotIn('status', [CompanyStatus::Won->value, CompanyStatus::Lost->value, CompanyStatus::Parked->value]);
    }

    /** Follow-up je na dnešek. */
    public function scopeDueToday(Builder $query): Builder
    {
        return $query->whereBetween('next_action_at', [now()->startOfDay(), now()->endOfDay()])
            ->whereNotIn('status', [CompanyStatus::Won->value, CompanyStatus::Lost->value, CompanyStatus::Parked->value]);
    }

    /** Follow-up čeká ve zbytku tohoto týdne — od zítřka do neděle. */
    public function scopeDueThisWeek(Builder $query): Builder
    {
        return $query->whereBetween('next_action_at', [now()->addDay()->startOfDay(), now()->endOfWeek()])
            ->whereNotIn('status', [CompanyStatus::Won->value, CompanyStatus::Lost->value, CompanyStatus::Parked->value]);
    }

    /**
     * Firmy, které čekají na první oslovení.
     *
     * Po importu rešerše je v tomhle stavu celý seznam, takže bez něj by
     * přehled „Dnes" hlásil, že není co dělat, i když je práce na měsíc.
     */
    public function scopeUntouched(Builder $query): Builder
    {
        return $query->where('status', CompanyStatus::New);
    }

    /**
     * Rozjednané firmy, u kterých se týden nic nestalo. Firma bez jediné
     * aktivity se sem počítá taky — do práce se dostala a spadla pod stůl.
     */
    public function scopeStale(Builder $query, int $days = 7): Builder
    {
        $threshold = now()->subDays($days);

        return $query->whereIn('status', array_column(CompanyStatus::active(), 'value'))
            ->where(fn (Builder $q) => $q
                ->whereNull('last_activity_at')
                ->orWhere('last_activity_at', '<', $threshold));
    }

    /** Pořadí pracovního seznamu: nejdřív termín, pak priorita. */
    public function scopeWorkOrder(Builder $query): Builder
    {
        return $query->orderByRaw('next_action_at is null')
            ->orderBy('next_action_at')
            ->orderBy('priority');
    }

    /*
    |--------------------------------------------------------------------------
    | Odvozené hodnoty
    |--------------------------------------------------------------------------
    */

    public function isOverdue(): bool
    {
        return $this->next_action_at !== null
            && $this->next_action_at->lt(now()->startOfDay())
            && ! $this->status->isClosed();
    }

    public function websiteUrl(): ?string
    {
        return Domain::toUrl($this->website);
    }

    /**
     * Firma se stejnou doménou. Zakládací formulář na ni upozorní, ale
     * nezakáže uložení — občas máme důvod vést dvě karty (jiná pobočka).
     */
    public static function withDomain(?string $website, ?int $exceptId = null): ?self
    {
        $domain = Domain::normalize($website);

        if ($domain === null) {
            return null;
        }

        return self::query()
            ->where('domain', $domain)
            ->when($exceptId !== null, fn (Builder $q) => $q->whereKeyNot($exceptId))
            ->first();
    }

    /**
     * Přepočet termínu dalšího kroku z aktivit.
     *
     * Platí nejbližší follow-up, který ještě nikdo nevyřídil. Vyřídí ho až
     * další kontakt s firmou, ne prostý běh času — jinak by propásnutý
     * follow-up při nejbližším uložení zmizel a přehled „Po termínu" by
     * nikdy nic neukázal.
     *
     * Termín ruší jen skutečný kontakt (e-mail, hovor, schůzka, LinkedIn,
     * reakce na poptávku). Poznámka ani úkol ho nechají být — dopsat si
     * k firmě zjištění není totéž co ozvat se.
     *
     * Volá se z observeru po každé změně aktivity. Nikdy se nedopisuje ručně,
     * jinak by po smazání aktivity zůstal termín viset.
     */
    public function recalculateNextAction(): void
    {
        $lastContact = $this->activities()
            ->whereIn('type', array_column(ActivityType::outreach(), 'value'))
            ->max('happened_at');

        $next = $this->activities()
            ->whereNotNull('follow_up_at')
            ->when($lastContact !== null, fn (Builder $q) => $q->where('follow_up_at', '>', $lastContact))
            ->min('follow_up_at');

        $this->forceFill([
            'next_action_at' => $next,
            'last_activity_at' => $this->activities()->max('happened_at'),
        ])->saveQuietly();
    }

    /**
     * Posune připomínku o zadaný počet dní ode dneška.
     *
     * Termín nedržíme na firmě napřímo — je odvozený z follow-upů na aktivitách
     * (viz recalculateNextAction), takže by ho první uložená aktivita přepsala.
     * Odklad proto posouvá tu aktivitu, ze které současný termín vzešel.
     */
    public function snooze(int $days): void
    {
        $source = $this->activities()
            ->whereNotNull('follow_up_at')
            ->when($this->next_action_at !== null, fn (Builder $q) => $q->where('follow_up_at', $this->next_action_at))
            ->latest('id')
            ->first();

        // Odkládá se ode dneška, ne od původního termínu. Odložit propásnutý
        // follow-up o tři dny do minulosti by nedávalo smysl.
        $this->scheduleFollowUp(now()->addDays($days)->setTime(9, 0), $source);
    }

    /**
     * Naplánuje připomínku na konkrétní čas. Bez zdrojové aktivity ji zapíše
     * jako úkol, ať je v časové ose vidět, kdo a kdy termín nasadil.
     */
    public function scheduleFollowUp(\DateTimeInterface $when, ?Activity $source = null): void
    {
        if ($source !== null) {
            $source->update(['follow_up_at' => $when]);

            return;
        }

        $this->activities()->create([
            'user_id' => Auth::id(),
            'type' => ActivityType::Task,
            'subject' => 'Naplánován follow-up',
            'happened_at' => now(),
            'follow_up_at' => $when,
        ]);
    }

    protected static function booted(): void
    {
        // Doména je odvozená hodnota. Kdyby se plnila jen ve formuláři,
        // import z CSV ani strojové zakládání by ji nenastavily a hledání
        // duplicit by přestalo fungovat právě tam, kde je nejvíc potřeba.
        static::saving(function (self $company): void {
            $company->domain = Domain::normalize($company->website);
        });
    }

    protected function casts(): array
    {
        return [
            'segment' => CompanySegment::class,
            'status' => CompanyStatus::class,
            'priority' => Priority::class,
            'source' => CompanySource::class,
            'next_action_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }
}
