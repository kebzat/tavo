<?php

namespace App\Models;

use App\Enums\AutomationType;
use App\Enums\VaultType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Automatizace klienta. Zatím jen připomínky vyzvednutí zásilek v Upgates
 * (App\Support\Automations\ParcelPickup): engine zakládá zprávy
 * (AutomationEvent) a Upgates si je každou hodinu stáhne jako CSV import
 * (`csvUrl()`), změní stav objednávky a pošle e-mail nebo SMS sám.
 *
 * Technické nastavení (přístup k Zaslatu, export, názvy stavů) mění jen
 * správce v nástrojích, klient v /muj-ucet jen `CLIENT_SETTINGS`.
 * Viz docs/AUTOMATIZACE.md.
 */
class Automation extends Model
{
    protected $guarded = [];

    /** Adresa exportu je tajná (obsahuje klíč Upgates), token dává přístup k CSV. */
    protected $hidden = ['order_export_url', 'import_token'];

    /** Co smí měnit klient sám. Zbytek nastavení je technický. */
    public const CLIENT_SETTINGS = ['send_reminder', 'send_last_day', 'send_returned', 'remind_days_before'];

    public const DEFAULT_SETTINGS = [
        'vault_entry_id' => null,
        'status_sent' => 'Odeslána',
        'status_reminder' => 'Čeká na vyzvednutí',
        'status_last_day' => 'Poslední den k vyzvednutí',
        'status_returned' => 'Vráceno',
        // Stav, který v Upgates spouští fakturu a žádost o hodnocení. U vrácené zásilky nesmí zůstat.
        'status_resolved' => 'Vyřešeno',
        'source_statuses' => ['Odeslána', 'Čeká na vyzvednutí', 'Poslední den k vyzvednutí'],
        'remind_days_before' => 2,
        'send_reminder' => true,
        'send_last_day' => true,
        'send_returned' => true,
        // Den zapnutí: připomínka jen u zásilek, které na výdejní místo dorazily od něj.
        'reminders_since' => null,
        // Kam web nahrává změny stavů: přihlášení z trezoru a číslo importu objednávek v Upgates.
        'upgates_vault_entry_id' => null,
        'upgates_import_id' => null,
        // Úložní doba (dny od příchodu na výdejní místo), když ji dopravce neřekne sám.
        'carrier_storage_days' => ['zasilkovna' => 7, 'ppl' => 7, 'dpd' => 7, 'default' => 7],
    ];

    protected function casts(): array
    {
        return [
            'type' => AutomationType::class,
            'enabled' => 'boolean',
            'settings' => 'array',
            'order_export_url' => 'encrypted',
            'last_run_at' => 'datetime',
            'snapshot' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $automation): void {
            $automation->import_token ??= self::newToken();
            $automation->settings = array_replace(self::DEFAULT_SETTINGS, $automation->settings ?? []);
        });

        // Po zapnutí připomínat jen zásilky, které na výdejní místo dorazí od teď:
        // starším klient připomínky posílal ručně a druhou by dostali zbytečně.
        static::saving(function (self $automation): void {
            $turnedOn = $automation->enabled && (! $automation->exists || $automation->isDirty('enabled'));
            $given = $automation->exists ? null : (($automation->settings ?? [])['reminders_since'] ?? null);

            if ($turnedOn && $given === null) {
                $automation->settings = [...($automation->settings ?? []), 'reminders_since' => now('Europe/Prague')->toDateString()];
            }
        });
    }

    public static function newToken(): string
    {
        return Str::random(40);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AutomationEvent::class);
    }

    public function vaultEntry(): ?VaultEntry
    {
        $id = $this->setting('vault_entry_id');

        return $id ? VaultEntry::query()->whereKey($id)->where('type', VaultType::ApiKey)->first() : null;
    }

    /** Hodnota nastavení, chybějící klíč vezme výchozí. */
    public function setting(string $key): mixed
    {
        return ($this->settings ?? [])[$key] ?? self::DEFAULT_SETTINGS[$key] ?? null;
    }

    /** Název stavu v Upgates bez mezer okolo (Upgates je v názvech občas nechává). */
    public function status(string $key): string
    {
        return trim((string) $this->setting('status_'.$key));
    }

    /** @return list<string> stavy, ze kterých smí automatizace objednávku přepnout */
    public function sourceStatuses(): array
    {
        return array_values(array_filter(array_map(fn ($status): string => trim((string) $status), (array) $this->setting('source_statuses'))));
    }

    public function storageDays(?string $carrier): int
    {
        $days = (array) $this->setting('carrier_storage_days');

        return max(1, (int) ($days[strtolower((string) $carrier)] ?? $days['default'] ?? 7));
    }

    /**
     * Adresy XML exportů objednávek z Upgates (oddělené čárkou nebo řádkem).
     * První při víc adresách = kompletní export, stahuje se nejvýš jednou
     * za 6 h. Další (posledních 24 h) se stahují při každém běhu.
     *
     * @return list<string>
     */
    public function exportUrls(): array
    {
        return array_values(array_filter(preg_split('/[\s,]+/', (string) $this->order_export_url) ?: []));
    }

    /** Přihlášení do administrace Upgates z trezoru (druh Upgates, stejný klient), kterým web nahrává import. */
    public function upgatesEntry(): ?VaultEntry
    {
        $id = $this->setting('upgates_vault_entry_id');

        return $id ? VaultEntry::query()->whereKey($id)->where('type', VaultType::Upgates)->where('client_id', $this->client_id)->first() : null;
    }

    /** Adresa CSV čekajících změn stavů (kontrola, ruční import). Chrání ji token. */
    public function csvUrl(): string
    {
        return route('automations.csv', ['token' => $this->import_token]);
    }

    /** Automatizace, které smí vidět uživatel: správce všechny, klient jen svoje. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->where('client_id', $user->isClient() ? $user->client_id : 0);
    }

    /** @return list<array{order: string, carrier: ?string, stored_until: ?string, days_left: ?int, status: ?string, sent: list<string>}> */
    public function parcels(): array
    {
        return array_values((array) ($this->snapshot['parcels'] ?? []));
    }
}
