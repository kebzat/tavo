<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Enums\WorkArea;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;

#[Fillable(['name', 'email', 'password', 'role', 'client_id', 'is_reviewer', 'billing_area'])]
#[Hidden(['password', 'remember_token'])]
/**
 * Přihlášení do obou panelů vyžaduje kód z aplikace (Google Authenticator,
 * 1Password…), viz oba PanelProvidery. Tajný klíč i záložní
 * kódy drží traity Filamentu šifrovaně ve sloupcích app_authentication_*.
 */
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, Notifiable;

    /**
     * Do administrace se dostane každý účet týmu. Co uvnitř uvidí, rozhoduje
     * role — správce vše, redaktor jen obsah (viz App\Filament\Concerns\OnlyForAdmins).
     *
     * Panel interních nástrojů je jiný případ: obsahuje podklady k cizím
     * zakázkám, takže je celý jen pro správce.
     *
     * Klient smí jen do svého účtu (`client`, /muj-ucet) a jen s přiřazeným
     * klientem. Správce tam smí kvůli náhledu.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'tools' => $this->isAdmin(),
            'client' => $this->isAdmin() || ($this->isClient() && $this->client_id !== null),
            default => ! $this->isClient(),
        };
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isClient(): bool
    {
        return $this->role === UserRole::Client;
    }

    /** Klient TAVEO, ke kterému účet patří (jen u role Klient). */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Lidé z TAVEO, bez účtů klientů. Pro souhrny a výběry v nástrojích. */
    public function scopeStaff(Builder $query): Builder
    {
        return $query->where('role', '!=', UserRole::Client->value);
    }

    /** Kdo kontroluje audity, nabídky a reporty před odesláním. Viz App\Models\Concerns\HasReviews. */
    public function scopeReviewers(Builder $query): Builder
    {
        return $query->where('is_reviewer', true)->orderBy('id');
    }

    /**
     * Nikdo nesmaže sám sebe — ani hromadnou akcí v tabulce, kde se
     * neuplatní skrytí tlačítka na detailu. U posledního správce by se
     * jinak ke správě uživatelů a nastavení už nikdo nedostal.
     *
     * Vrácené false operaci tiše zruší (bez výjimky a rozbité obrazovky).
     */
    protected static function booted(): void
    {
        static::deleting(fn (self $user): ?bool => $user->getKey() === Auth::id() ? false : null);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_reviewer' => 'boolean',
            'billing_area' => WorkArea::class,
        ];
    }
}
