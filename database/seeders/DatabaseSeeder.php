<?php

namespace Database\Seeders;

use App\Models\Founder;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@tavo.cz'],
            [
                'name' => 'TAVO Admin',
                'password' => Hash::make('tavo-admin-2026'),
                'email_verified_at' => now(),
            ],
        );

        $this->call(ContentSeeder::class);

        $this->attachFounderPhoto();
    }

    /**
     * Společná fotka zakladatelů z designu → media kolekce prvního zakladatele.
     * Slouží jako výchozí vizuál sekce „Lidé"; klient ji přepíše ve Filamentu.
     */
    private function attachFounderPhoto(): void
    {
        $photo = database_path('seeders/assets/pavel-tom.png');
        $founder = Founder::query()->orderBy('order_column')->first();

        if (! $founder || ! is_file($photo) || $founder->getFirstMedia(Founder::MEDIA_PHOTO)) {
            return;
        }

        $founder
            ->addMedia($photo)
            ->preservingOriginal()
            ->withCustomProperties(['alt' => 'Pavel a Tom, zakladatelé TAVO'])
            ->toMediaCollection(Founder::MEDIA_PHOTO);
    }
}
