<?php

namespace Tests\Feature;

use App\Filament\Resources\CaseStudies\Pages\EditCaseStudy;
use App\Models\CaseStudy;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Obrázky nahrané v administraci musí skončit na disku `public`. Na disku `local`
 * (storage/app/private) se v administraci tváří jako v pořádku, ale na webu jsou
 * rozbité — přesně to se stalo u reference ChrudimLab.
 */
class MediaUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_filament_uklada_soubory_na_verejny_disk(): void
    {
        $this->assertSame('public', config('filament.default_filesystem_disk'));
    }

    public function test_nahrany_obrazek_reference_je_dostupny_z_webu(): void
    {
        Storage::fake('public');
        $this->seed(ContentSeeder::class);
        $this->actingAs(User::factory()->create());

        $case = CaseStudy::query()->firstOrFail();

        Livewire::test(EditCaseStudy::class, ['record' => $case->getRouteKey()])
            ->fillForm(['thumb' => [UploadedFile::fake()->image('nahled.jpg', 1200, 900)]])
            ->call('save')
            ->assertHasNoFormErrors();

        $media = $case->refresh()->getFirstMedia(CaseStudy::MEDIA_THUMB);

        $this->assertNotNull($media, 'Obrázek se vůbec nepřipojil k referenci.');
        $this->assertSame('public', $media->disk);
        $this->assertSame($media->id.'/'.$media->file_name, $case->thumbPath());
        $this->assertStringStartsWith('/storage/', parse_url($case->thumbImage()['src'], PHP_URL_PATH));
        Storage::disk('public')->assertExists($media->id.'/'.$media->file_name);
    }
}
