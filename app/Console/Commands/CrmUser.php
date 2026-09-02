<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Založení účtu do CRM. Registrace v aplikaci není a být nemá — účty jsou
 * dva a zakládají se na serveru.
 */
class CrmUser extends Command
{
    protected $signature = 'crm:user
        {name? : Jméno}
        {email? : E-mail}
        {--password= : Heslo (bez něj se vygeneruje a vypíše)}';

    protected $description = 'Založí uživatele s přístupem do CRM';

    public function handle(): int
    {
        $name = (string) ($this->argument('name') ?: $this->ask('Jméno'));
        $email = (string) ($this->argument('email') ?: $this->ask('E-mail'));

        if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Potřebuju jméno a platný e-mail.');

            return self::FAILURE;
        }

        if (User::where('email', $email)->exists()) {
            $this->warn("Účet {$email} už existuje — nic se nemění.");

            return self::SUCCESS;
        }

        $password = (string) ($this->option('password') ?: Str::password(16));

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            // Panel nástrojů je celý jen pro správce, viz User::canAccessPanel().
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
        ]);

        $this->info("Účet {$email} založen.");

        if (! $this->option('password')) {
            $this->warn("Heslo: {$password}");
            $this->warn('Uložte si ho — znovu se nezobrazí.');
        }

        return self::SUCCESS;
    }
}
