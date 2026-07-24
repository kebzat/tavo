<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Do administrace se dostane každý účet. Co uvnitř uvidí, rozhoduje role —
     * správce vše, redaktor jen obsah (viz App\Filament\Concerns\OnlyForAdmins).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
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
        ];
    }
}
