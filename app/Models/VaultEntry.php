<?php

namespace App\Models;

use App\Enums\VaultType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PragmaRX\Google2FA\Google2FA;

/**
 * Jeden přístup v trezoru (FTP, WordPress, Shoptet…). Heslo, aplikační heslo,
 * kód 2FA a poznámka jsou v databázi zašifrované klíčem aplikace, takže je
 * neprozradí ani záloha databáze. Agent je nikdy nedostane: `toAgent()` je
 * vynechává a práci s nimi dělá web (App\Support\Vault). Viz docs/TREZOR.md.
 */
class VaultEntry extends Model
{
    protected $guarded = [];

    /** Pro jistotu ani v JSONu, kdyby model někde skončil celý. */
    protected $hidden = ['secret', 'app_password', 'totp_secret', 'notes'];

    /** Úrovně `agent_access` od nejmenší. */
    public const ACCESS_LEVELS = ['none', 'read', 'approve', 'write'];

    protected function casts(): array
    {
        return [
            'type' => VaultType::class,
            'secret' => 'encrypted',
            'app_password' => 'encrypted',
            'totp_secret' => 'encrypted',
            'notes' => 'encrypted',
            'settings' => 'array',
            'last_used_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(VaultUpload::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(VaultLog::class);
    }

    public function scopeForAgent(Builder $query): Builder
    {
        return $query->where('agent_access', '!=', 'none');
    }

    /** Smí agent aspoň tolik? Úrovně jdou po sobě: none < read < approve < write. */
    public function agentAllows(string $level): bool
    {
        $have = array_search($this->agent_access, self::ACCESS_LEVELS, true);

        return $have !== false && $have >= array_search($level, self::ACCESS_LEVELS, true);
    }

    public function agentAccessLabel(): string
    {
        return $this->type->agentAccessOptions()[$this->agent_access] ?? 'Nic, přístup je jen pro lidi';
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return ($this->settings ?? [])[$key] ?? $default;
    }

    /** Jak se připojit k FTP přístupu: ftp, ftps, nebo sftp (SSH). */
    public function ftpProtocol(): string
    {
        $protocol = $this->setting('protocol');

        if (array_key_exists((string) $protocol, self::ftpProtocols())) {
            return $protocol;
        }

        return self::guessFtpProtocol((string) $this->url, (string) $this->username, $this->setting('port'), $this->setting('tls', true));
    }

    public static function ftpProtocols(): array
    {
        return [
            'sftp' => 'SFTP (přes SSH)',
            'ftps' => 'FTP se šifrováním (FTPS)',
            'ftp' => 'FTP bez šifrování',
        ];
    }

    /** Přístupy uložené před výběrem protokolu: SFTP prozradí adresa, port 22 nebo uživatel `sftp_…` (zcom). */
    public static function guessFtpProtocol(string $url, string $username, mixed $port, mixed $tls): string
    {
        if (str_starts_with(strtolower(trim($url)), 'sftp://') || (int) $port === 22 || str_starts_with(strtolower($username), 'sftp')) {
            return 'sftp';
        }

        return $tls ? 'ftps' : 'ftp';
    }

    public function loginUrl(): ?string
    {
        if (filled($this->setting('login_url'))) {
            return $this->setting('login_url');
        }

        $path = $this->type->loginPath();

        return $path && filled($this->url) ? rtrim($this->url, '/').$path : null;
    }

    /** Aktuální kód 2FA (6 číslic), pokud je uložený tajný klíč z aplikace. */
    public function totpCode(): ?string
    {
        if (blank($this->totp_secret)) {
            return null;
        }

        return (new Google2FA)->getCurrentOtp(strtoupper(str_replace(' ', '', $this->totp_secret)));
    }

    /** Co o přístupu smí vědět agent. Žádné heslo, klíč ani poznámka. */
    public function toAgent(): array
    {
        return array_filter([
            'id' => $this->getKey(),
            'klient' => $this->client?->slug,
            'druh' => $this->type->value,
            'nazev' => $this->name,
            'adresa' => $this->url,
            'uzivatel' => $this->username,
            'protokol' => $this->type === VaultType::Ftp ? $this->ftpProtocol() : null,
            'slozka' => $this->type === VaultType::Ftp ? $this->setting('root', '/') : null,
            'smi' => $this->agentAccessLabel(),
            'umi' => $this->agentCommands(),
        ], fn ($value) => $value !== null && $value !== []);
    }

    /** Příkazy `taveo`, které u přístupu fungují (nápověda pro agenta). */
    public function agentCommands(): array
    {
        $commands = [];

        if ($this->type === VaultType::Ftp && $this->agentAllows('read')) {
            $commands = ['taveo ftp <id> [cesta]', 'taveo ftp-soubor <id> <cesta>', 'taveo ftp-stahnout <id> <cesta> <složka>'];

            if ($this->agentAllows('approve')) {
                $commands[] = 'taveo ftp-nahrat <id> "popis" <složka> <soubor>…';
            }
        }

        if ($this->type === VaultType::WordPress && $this->agentAllows('read') && filled($this->app_password)) {
            $commands[] = 'taveo wp <id> GET /wp/v2/plugins';
        }

        if ($this->type->isLogin() && $this->agentAllows('read') && filled($this->secret)) {
            $commands[] = 'taveo prihlasit <id>';
        }

        return $commands;
    }

    /** Zapíše použití do historie a posune „naposledy použito“. */
    public function record(string $actor, string $action, ?string $detail = null): VaultLog
    {
        $this->forceFill(['last_used_at' => now()])->saveQuietly();

        return VaultLog::query()->create([
            'vault_entry_id' => $this->getKey(),
            'entry_name' => $this->name,
            'client_id' => $this->client_id,
            'actor' => $actor,
            'action' => $action,
            'detail' => $detail !== null ? mb_substr($detail, 0, 2000) : null,
            'ip' => request()?->ip(),
        ]);
    }
}
