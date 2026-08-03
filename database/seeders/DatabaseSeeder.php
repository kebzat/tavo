<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\CaseStudy;
use App\Models\Founder;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->createAdmin();

        $this->call(ContentSeeder::class);

        $this->attachFounderPhoto();
        $this->attachCaseStudyImages();
    }

    /**
     * Založí správce, ale jen pokud ještě neexistuje.
     *
     * Dřív tu bylo updateOrCreate s heslem natvrdo — každé další spuštění
     * seederu tím vrátilo heslo na hodnotu z repozitáře, takže změna hesla
     * v administraci nevydržela do příštího nasazení.
     *
     * Heslo se bere z ADMIN_PASSWORD; když chybí, vygeneruje se náhodné
     * a jednorázově vypíše do konzole. Do repozitáře žádné nepatří.
     */
    private function createAdmin(): void
    {
        $email = (string) env('ADMIN_EMAIL', 'admin@taveo.cz');

        if (User::query()->where('email', $email)->exists()) {
            $this->command?->info("Správce {$email} už existuje — heslo ponecháno beze změny.");

            return;
        }

        $password = (string) (env('ADMIN_PASSWORD') ?: Str::password(16));

        User::create([
            'name' => (string) env('ADMIN_NAME', 'Správce'),
            'email' => $email,
            'password' => $password,
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
        ]);

        $this->command?->warn("Vytvořen správce: {$email}");
        $this->command?->warn("Heslo: {$password}");
        $this->command?->warn('Uložte si ho — znovu se nezobrazí.');
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
            ->withCustomProperties(['alt' => 'Pavel a Tom, zakladatelé Taveo'])
            ->toMediaCollection(Founder::MEDIA_PHOTO);
    }

    /**
     * Obrázky referencí. Soubor „<slug>-43" (poměr 4:3) jde do výpisu jako náhled,
     * pole „gallery" je detailová galerie (libovolný počet). U webů je v galerii
     * jeden snímek webu, u Pavlových referencí několik reálných kreativ z jeho webu.
     */
    private function attachCaseStudyImages(): void
    {
        $refs = [
            'vcely-uhersko' => [
                'thumb' => 'vcely-43',
                'gallery' => [['vcely-168', 'Web a vizuální styl značky Včely Uhersko']],
            ],
            'chrudimlab' => [
                'thumb' => 'chrudimlab-43',
                'gallery' => [['chrudimlab-168', 'Úvodní stránka webu zubní laboratoře ChrudimLab']],
            ],
            'hopnjoy' => [
                'thumb' => 'hopnjoy-43',
                'gallery' => [['hopnjoy-168', "Úvodní stránka webu půjčovny skákacích hradů Hop'n'Joy"]],
            ],
            'ales-malinsky' => [
                'thumb' => 'malinsky-43',
                'gallery' => [['malinsky-168', 'Úvodní stránka webu realitního makléře Aleše Malinského']],
            ],
            'reklamni-grafika' => [
                'thumb' => 'grafika-43',
                'gallery' => [
                    ['grafika-g1', 'Reklamní grafika Realimo, rychlý výkup nemovitosti'],
                    ['grafika-g2', 'Náborový inzerát na pozici do výzkumu a vývoje'],
                    ['grafika-g3', 'Reklama na pohovku pro e-shop s nábytkem'],
                    ['grafika-g4', 'Infografika Svět cejlonu, proč pít modrý čaj'],
                ],
            ],
            'reels-video' => [
                'thumb' => 'reels-43',
                'gallery' => [
                    ['reels-g1', 'Reels pro Svět cejlonu, modrý čaj'],
                    ['reels-g2', 'Reels pro Fitmin, pamlsky pro psy'],
                    ['reels-g3', 'Reels pro Fitmin, krmivo pro psy'],
                    ['reels-g4', 'Reels pro Českou louku, čerstvé microgreens'],
                ],
            ],
        ];

        foreach ($refs as $slug => $ref) {
            $case = CaseStudy::query()->where('slug', $slug)->first();

            if (! $case) {
                continue;
            }

            $this->attachMedia(
                $case,
                CaseStudy::MEDIA_THUMB,
                database_path("seeders/assets/reference/{$ref['thumb']}.jpg"),
                $ref['gallery'][0][1],
            );

            // Galerie se needituje přepsáním — doplníme ji jen tehdy, když je prázdná.
            if ($case->getMedia(CaseStudy::MEDIA_GALLERY)->isEmpty()) {
                foreach ($ref['gallery'] as [$file, $alt]) {
                    $this->attachMedia(
                        $case,
                        CaseStudy::MEDIA_GALLERY,
                        database_path("seeders/assets/reference/{$file}.jpg"),
                        $alt,
                    );
                }
            }
        }
    }

    private function attachMedia(CaseStudy $case, string $collection, string $path, string $alt): void
    {
        if (! is_file($path)) {
            return;
        }

        if ($collection !== CaseStudy::MEDIA_GALLERY && $case->getFirstMedia($collection)) {
            return;
        }

        $case
            ->addMedia($path)
            ->preservingOriginal()
            ->withCustomProperties(['alt' => $alt])
            ->toMediaCollection($collection);
    }
}
