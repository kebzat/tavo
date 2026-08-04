<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Statická stránka byla jeden sloupec HTML z rich-text editoru. Nově se skládá
 * z bloků (`blocks`), aby šlo vedle textu použít i grafické sekce z designu —
 * obrázek s textem, statistiky, tmavý pruh, CTA.
 *
 * Původní obsah se nepřevádí do prázdna: přesune se do jediného bloku typu
 * `text`, takže stránka po migraci vypadá úplně stejně jako předtím.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->json('blocks')->nullable()->after('perex');
        });

        DB::table('pages')->orderBy('id')->each(function (object $page) {
            $content = trim((string) ($page->content ?? ''));

            DB::table('pages')->where('id', $page->id)->update([
                'blocks' => json_encode(
                    $content === '' ? [] : [['type' => 'text', 'data' => ['body' => $content]]],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ),
            ]);
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('content');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->longText('content')->nullable()->after('perex');
        });

        // Zpátky se vejde jen text — grafické bloky by v jednom HTML sloupci
        // stejně neměly jak přežít, takže se při rollbacku zahodí.
        DB::table('pages')->orderBy('id')->each(function (object $page) {
            $blocks = json_decode((string) ($page->blocks ?? '[]'), true) ?: [];

            $html = collect($blocks)
                ->where('type', 'text')
                ->pluck('data.body')
                ->filter()
                ->implode("\n");

            DB::table('pages')->where('id', $page->id)->update(['content' => $html ?: null]);
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('blocks');
        });
    }
};
