<?php

use App\Models\CaseStudy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Detail reference měl jeden velký vizuál (kolekce `hero`). Nahradila ho galerie,
 * do které jde nahrát libovolný počet obrázků. Původní vizuál se přesune do
 * galerie jako první položka, takže se nic neztratí.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('media')
            ->where('model_type', CaseStudy::class)
            ->where('collection_name', 'hero')
            ->update(['collection_name' => CaseStudy::MEDIA_GALLERY]);
    }

    public function down(): void
    {
        // Zpět bereme jen první obrázek galerie — víc se do jednoho vizuálu nevejde.
        $firstIds = DB::table('media')
            ->where('model_type', CaseStudy::class)
            ->where('collection_name', CaseStudy::MEDIA_GALLERY)
            ->orderBy('order_column')
            ->orderBy('id')
            ->get(['id', 'model_id'])
            ->groupBy('model_id')
            ->map(fn ($rows) => $rows->first()->id)
            ->values();

        DB::table('media')->whereIn('id', $firstIds)->update(['collection_name' => 'hero']);
    }
};
