<?php

namespace Database\Seeders;

use App\Models\CaseStudy;
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
        $this->attachCaseStudyImages();
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

    /**
     * Náhledy referencí jsou snímky obrazovky webů, o kterých reference mluví.
     * Soubor -43 má poměr 4:3 do výpisu, -168 poměr 16:8 na detail, takže se
     * v šabloně nic neořízne. Klient je ve Filamentu může kdykoliv přepsat.
     */
    private function attachCaseStudyImages(): void
    {
        $images = [
            'chrudimlab' => ['chrudimlab', 'Úvodní stránka webu zubní laboratoře ChrudimLab'],
            'hopnjoy' => ['hopnjoy', "Úvodní stránka webu půjčovny skákacích hradů Hop'n'Joy"],
            'ales-malinsky' => ['malinsky', 'Úvodní stránka webu realitního makléře Aleše Malinského'],
            'spravovane-eshopy' => ['cejlon', 'E-shop Svět cejlonu, jeden z účtů, kterým Pavel spravuje reklamu'],
        ];

        foreach ($images as $slug => [$file, $alt]) {
            $case = CaseStudy::query()->where('slug', $slug)->first();

            if (! $case) {
                continue;
            }

            $collections = [
                CaseStudy::MEDIA_THUMB => database_path("seeders/assets/reference/{$file}-43.jpg"),
                CaseStudy::MEDIA_HERO => database_path("seeders/assets/reference/{$file}-168.jpg"),
            ];

            foreach ($collections as $collection => $path) {
                if (! is_file($path) || $case->getFirstMedia($collection)) {
                    continue;
                }

                $case
                    ->addMedia($path)
                    ->preservingOriginal()
                    ->withCustomProperties(['alt' => $alt])
                    ->toMediaCollection($collection);
            }
        }
    }
}
