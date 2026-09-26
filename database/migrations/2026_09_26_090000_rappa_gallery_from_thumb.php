<?php

use App\Models\CaseStudy;
use App\Support\ResponsiveImage;
use Illuminate\Database\Migrations\Migration;

/**
 * RAPPA neměla na detailu žádný obrázek: galerie byla prázdná a pro screenshot
 * z tomaskebza.cz ji minulá migrace přeskočila (tam RAPPA není). Do galerie
 * se proto zkopíruje její náhled z výpisu.
 *
 * Jen když je galerie pořád prázdná, takže obrázky nahrané mezitím
 * v administraci zůstanou.
 */
return new class extends Migration
{
    public function up(): void
    {
        $case = CaseStudy::query()->where('slug', 'rappa')->first();
        $thumb = $case?->getFirstMedia(CaseStudy::MEDIA_THUMB);

        if (! $case || ! $thumb || $case->getMedia(CaseStudy::MEDIA_GALLERY)->isNotEmpty()) {
            return;
        }

        $copy = $thumb->copy($case, CaseStudy::MEDIA_GALLERY);

        ResponsiveImage::generate($copy->getPathRelativeToRoot());
    }

    public function down(): void
    {
        // Obrázek v galerii necháváme. Správce s ní mohl mezitím pracovat.
    }
};
