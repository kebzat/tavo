<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            // Panely vyžadují dvoufázové ověření. Testy, které nejdou přes
            // přihlašovací formulář, by bez klíče skončily na jeho nastavení.
            'app_authentication_secret' => 'JBSWY3DPEHPK3PXP',
        ];
    }

    /** Účet, který si dvoufázové ověření ještě nenastavil. */
    public function withoutTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'app_authentication_secret' => null,
            'app_authentication_recovery_codes' => null,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
