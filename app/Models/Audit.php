<?php

namespace App\Models;

use App\Enums\Crm\ActivityType;
use App\Enums\Crm\CompanyStatus;
use App\Support\AuditMarkdown;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Audit klientského webu. Sdílí se odkazem stejně jako checklist
 * a na sdílené stránce se s checklisty téhož klienta propojí.
 */
class Audit extends Model
{
    /**
     * Řádek, od kterého je text v omezeném režimu zamčený. Klient vidí,
     * co je nad ním, a z kapitol pod ním jen nadpisy s výzvou k hovoru.
     */
    public const LOCK_MARKER = '::: zámek';

    private const LOCK_PATTERN = '/^:::[ \t]*zámek[ \t]*$/mu';

    /** Náhledy odkazů z e-mailu a chatu otevření nejsou. */
    private const BOT_PATTERN = '/bot|crawl|spider|preview|facebookexternalhit|whatsapp|telegram|skype|slack|discord|curl|wget|python|headless|lighthouse/i';

    protected $guarded = [];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true)->whereNotNull('public_token');
    }

    /** Odkaz pro klienta. Null, dokud sdílení nezapneme. */
    public function publicUrl(): ?string
    {
        if (! $this->is_public || ! $this->public_token) {
            return null;
        }

        return route('audit.show', $this->public_token);
    }

    /** Náhled pro přihlášeného správce. Funguje i u auditu, který ještě nesdílíme. */
    public function previewUrl(): ?string
    {
        return $this->public_token ? route('audit.show', $this->public_token) : null;
    }

    /**
     * Text převedený do HTML a obsah pro boční navigaci.
     *
     * V omezeném režimu jen část nad značkou zámku. Kapitoly pod ní
     * se vrátí jako `locked`, stránka z nich ukáže jen nadpisy.
     * Bez omezeného režimu značka z textu zmizí a klient vidí všechno.
     *
     * @return array{html: string, toc: list<array{id: string, title: string}>, locked: list<string>}
     */
    public function rendered(): array
    {
        $parts = preg_split(self::LOCK_PATTERN, (string) $this->body, 2);

        if (! $this->is_teaser || count($parts) < 2) {
            return AuditMarkdown::render(implode("\n", $parts)) + ['locked' => []];
        }

        return AuditMarkdown::render($parts[0]) + [
            'locked' => array_column(AuditMarkdown::render($parts[1])['toc'], 'title'),
        ];
    }

    /** Claude audit právě přepisuje. Po čtvrt hodině se to bere jako zaseknuté. */
    public function isBeingWritten(): bool
    {
        return $this->ai_status === 'running' && $this->updated_at?->gt(now()->subMinutes(15));
    }

    public function hasLockMarker(): bool
    {
        return (bool) preg_match(self::LOCK_PATTERN, (string) $this->body);
    }

    /**
     * Zapíše otevření odkazu. První otevření (a další po půl dni ticha)
     * se propíše do CRM jako aktivita s follow-upem na příští pracovní
     * den, protože právě tehdy má smysl zavolat.
     *
     * Roboti a náhledy odkazů se nepočítají, správce si audit otevírá
     * přihlášený a ten volající nepředá.
     */
    public function recordView(?string $userAgent): void
    {
        if (blank($userAgent) || preg_match(self::BOT_PATTERN, $userAgent)) {
            return;
        }

        $worthLogging = $this->last_viewed_at === null || $this->last_viewed_at->lt(now()->subHours(12));

        $this->forceFill([
            'view_count' => $this->view_count + 1,
            'first_viewed_at' => $this->first_viewed_at ?? now(),
            'last_viewed_at' => now(),
        ])->saveQuietly();

        $company = $this->client?->crmCompany;

        if (! $worthLogging || $company === null) {
            return;
        }

        $closed = in_array($company->status, [CompanyStatus::Won, CompanyStatus::Lost], true);

        $company->activities()->create([
            'type' => ActivityType::Note,
            'subject' => $this->view_count === 1 ? 'Otevřeli audit poprvé' : 'Znovu otevřeli audit',
            'body' => $this->title.' · otevřeno '.$this->view_count.'×',
            'happened_at' => now(),
            'follow_up_at' => $closed ? null : now()->nextWeekday()->setTime(9, 0),
        ]);
    }

    /**
     * Dlaždice s čísly bez prázdných řádků, které v administraci zůstanou
     * po odebrání hodnoty.
     *
     * @return list<array{value: string, label: string}>
     */
    public function highlightTiles(): array
    {
        return collect($this->highlights ?? [])
            ->filter(fn ($tile): bool => filled($tile['value'] ?? null))
            ->map(fn (array $tile): array => [
                'value' => (string) $tile['value'],
                'label' => (string) ($tile['label'] ?? ''),
            ])
            ->values()
            ->all();
    }

    protected static function booted(): void
    {
        // Token přidělíme hned při založení, ať je odkaz připravený dřív,
        // než ho někdo zapne. Stejně jako u checklistu.
        static::saving(function (self $audit): void {
            if (! $audit->public_token) {
                $audit->public_token = Str::random(40);
            }
        });

        // Checklist z auditu vzniká skrytý, v omezeném režimu by prozradil
        // postup oprav. Jakmile klient dostane plnou verzi, dostane i jej.
        static::saved(function (self $audit): void {
            if ($audit->is_public && ! $audit->is_teaser && $audit->wasChanged('is_teaser') && $audit->client) {
                $audit->client->checklists()->forClients()->where('is_public', false)->update(['is_public' => true]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'audited_at' => 'date',
            'highlights' => 'array',
            'is_public' => 'boolean',
            'is_teaser' => 'boolean',
            'first_viewed_at' => 'datetime',
            'last_viewed_at' => 'datetime',
        ];
    }
}
