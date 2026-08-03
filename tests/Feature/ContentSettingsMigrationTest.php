<?php

namespace Tests\Feature;

use App\Support\ContentSettingsMigration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelSettings\SettingsRepositories\SettingsRepository;
use Tests\TestCase;

/**
 * Pojistka proti tomu, aby nasazení přepsalo text, který si správce upravil
 * v administraci. Nasazení pouští `artisan migrate`, takže tohle chování
 * rozhoduje o tom, jestli klient o svoje úpravy přijde.
 *
 * @see docs/CONTENT-MODEL.md
 */
class ContentSettingsMigrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Zkušební migrace, která zpřístupní chráněné metody základní třídy.
     * Návratový typ schválně neuvádíme, ať analyzátor vidí i metody `call*`.
     */
    private function migration()
    {
        return new class extends ContentSettingsMigration
        {
            public function up(): void {}

            /** @param array<string, mixed> $values */
            public function callAdd(array $values): void
            {
                $this->add($values);
            }

            /** @param array<string, mixed> $values */
            public function callReplace(array $values): void
            {
                $this->replace($values);
            }

            public function callReplaceIfUntouched(string $key, mixed $expected, mixed $new): void
            {
                $this->replaceIfUntouched($key, $expected, $new);
            }
        };
    }

    public function test_add_zaklada_nove_pole(): void
    {
        $this->migration()->callAdd(['home.pokus' => 'výchozí']);

        $this->assertSame('výchozí', $this->value('pokus'));
    }

    public function test_add_neprepise_upravu_ze_administrace(): void
    {
        $migration = $this->migration();
        $migration->callAdd(['home.pokus' => 'výchozí']);

        $migration->callReplace(['home.pokus' => 'ruční úprava správce']);
        $migration->callAdd(['home.pokus' => 'nová výchozí hodnota z repa']);

        $this->assertSame('ruční úprava správce', $this->value('pokus'));
    }

    public function test_replace_if_untouched_prepise_nedotcenou_hodnotu(): void
    {
        $migration = $this->migration();
        $migration->callAdd(['home.pokus' => 'původní věta s překlepem']);

        $migration->callReplaceIfUntouched('home.pokus', 'původní věta s překlepem', 'opravená věta');

        $this->assertSame('opravená věta', $this->value('pokus'));
    }

    public function test_replace_if_untouched_necha_byt_upravu_ze_administrace(): void
    {
        $migration = $this->migration();
        $migration->callAdd(['home.pokus' => 'původní věta s překlepem']);
        $migration->callReplace(['home.pokus' => 'co si tam napsal Pavel']);

        $migration->callReplaceIfUntouched('home.pokus', 'původní věta s překlepem', 'opravená věta');

        $this->assertSame('co si tam napsal Pavel', $this->value('pokus'));
    }

    private function value(string $property): mixed
    {
        return app(SettingsRepository::class)
            ->getPropertyPayload('home', $property);
    }
}
