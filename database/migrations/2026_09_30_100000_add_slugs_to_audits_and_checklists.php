<?php

/*
|--------------------------------------------------------------------------
| Čitelné adresy auditů a checklistů
|--------------------------------------------------------------------------
| Místo /audit/kb3mcK5MHc… je adresa /audit/svet-cejlonu. Token zůstává:
| odkazy rozeslané před touhle změnou dál fungují a přesměrují na slug.
*/

use App\Models\Audit;
use App\Models\Checklist;
use App\Support\UniqueSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('title');
        });

        Schema::table('checklists', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
        });

        // Starší záznamy dostanou slug podle klienta. Nejstarší záznam
        // dostane holý název, další s pořadovým číslem.
        $this->backfill(Audit::class, 'title');
        $this->backfill(Checklist::class, 'name');
    }

    public function down(): void
    {
        Schema::table('audits', fn (Blueprint $table) => $table->dropColumn('slug'));
        Schema::table('checklists', fn (Blueprint $table) => $table->dropColumn('slug'));
    }

    /** @param  class-string<Model>  $model */
    private function backfill(string $model, string $titleColumn): void
    {
        $model::query()->whereNull('slug')->with('client')->orderBy('id')->each(function ($record) use ($model, $titleColumn): void {
            $slug = UniqueSlug::for($record, $record->client?->name ?? $record->{$titleColumn});

            // Bez eventů: uložení by spustilo hooky modelu (u auditu zveřejnění checklistů).
            DB::table((new $model)->getTable())->where('id', $record->getKey())->update(['slug' => $slug]);
        });
    }
};
