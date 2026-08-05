<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Detail reference měl spodní polovinu natvrdo: role, výsledky, poznámku a citaci.
 * U každé reference sedí něco jiného, takže se z těch sekcí stávají bloky a dají
 * se skládat stejně jako na statické stránce.
 *
 * Úvod zůstává pevný, protože ho má každá reference stejný: hlavička s galerií,
 * pruh s údaji o projektu a zadání. Bloky začínají pod ním.
 *
 * Stávající obsah se převádí 1:1, aby reference vypadaly úplně stejně jako dosud:
 * role → blok „Odrážky ve sloupcích", výsledky → „Statistiky" v cihlové,
 * citace → „Citace", poznámka pod čarou → k tomu bloku, u kterého dosud visela.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_studies', function (Blueprint $table) {
            $table->json('blocks')->nullable()->after('problem_points');
        });

        DB::table('case_studies')->orderBy('id')->each(function (object $case) {
            DB::table('case_studies')->where('id', $case->id)->update([
                'blocks' => json_encode($this->blocksFor($case), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        });

        Schema::table('case_studies', function (Blueprint $table) {
            $table->dropColumn([
                'roles_title', 'roles_perex',
                'marketing_title', 'marketing_items',
                'dev_title', 'dev_items',
                'results', 'quote', 'quote_author', 'disclaimer',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('case_studies', function (Blueprint $table) {
            $table->string('roles_title')->nullable();
            $table->text('roles_perex')->nullable();
            $table->string('marketing_title')->nullable();
            $table->json('marketing_items')->nullable();
            $table->string('dev_title')->nullable();
            $table->json('dev_items')->nullable();
            $table->json('results')->nullable();
            $table->text('quote')->nullable();
            $table->string('quote_author')->nullable();
            $table->text('disclaimer')->nullable();
        });

        // Zpátky se vejde jen to, co mělo dřív svůj sloupec. Bloky, které mezitím
        // někdo přidal navíc (text, karty, před a po), při rollbacku zaniknou.
        DB::table('case_studies')->orderBy('id')->each(function (object $case) {
            $blocks = collect(json_decode((string) ($case->blocks ?? '[]'), true) ?: []);

            $bullets = $blocks->firstWhere('type', 'bullets')['data'] ?? [];
            $metrics = $blocks->firstWhere('type', 'metrics')['data'] ?? [];
            $quote = $blocks->firstWhere('type', 'quote')['data'] ?? [];
            $columns = collect($bullets['columns'] ?? []);

            DB::table('case_studies')->where('id', $case->id)->update([
                'roles_title' => $bullets['title'] ?? null,
                'roles_perex' => $bullets['perex'] ?? null,
                'marketing_title' => $columns->get(0)['label'] ?? null,
                'marketing_items' => isset($columns->get(0)['items']) ? json_encode($columns->get(0)['items'], JSON_UNESCAPED_UNICODE) : null,
                'dev_title' => $columns->get(1)['label'] ?? null,
                'dev_items' => isset($columns->get(1)['items']) ? json_encode($columns->get(1)['items'], JSON_UNESCAPED_UNICODE) : null,
                'results' => filled($metrics['items'] ?? null) ? json_encode($metrics['items'], JSON_UNESCAPED_UNICODE) : null,
                'quote' => $quote['text'] ?? null,
                'quote_author' => $quote['author'] ?? null,
                'disclaimer' => $bullets['note'] ?? $metrics['note'] ?? null,
            ]);
        });

        Schema::table('case_studies', function (Blueprint $table) {
            $table->dropColumn('blocks');
        });
    }

    /**
     * Sekce jedné reference přepsané do bloků. Pořadí odpovídá tomu, v jakém se
     * dosud vysázely, takže se na webu nic nepřeskládá.
     *
     * @return array<int, array{type: string, data: array<string, mixed>}>
     */
    private function blocksFor(object $case): array
    {
        $blocks = [];
        $disclaimer = filled($case->disclaimer ?? null) ? $case->disclaimer : null;

        $columns = collect([
            ['label' => $case->marketing_title ?? null, 'items' => $this->decode($case->marketing_items ?? null)],
            ['label' => $case->dev_title ?? null, 'items' => $this->decode($case->dev_items ?? null)],
        ])->filter(fn (array $column): bool => filled($column['items']))->values()->all();

        $results = $this->decode($case->results ?? null);

        if ($columns) {
            $blocks[] = ['type' => 'bullets', 'data' => array_filter([
                'tone' => 'ink',
                'title' => $case->roles_title ?? null,
                'perex' => $case->roles_perex ?? null,
                'columns' => $columns,
                // Poznámka visela pod rolemi, dokud reference neměla metriky.
                'note' => $results ? null : $disclaimer,
            ], fn ($value) => filled($value))];
        }

        if ($results) {
            $blocks[] = ['type' => 'metrics', 'data' => array_filter([
                'tone' => 'brick',
                'title' => 'Výsledek',
                'items' => $results,
                'note' => $disclaimer,
            ], fn ($value) => filled($value))];
        }

        // Bez rolí i bez metrik neměla poznámka kam patřit a dostávala vlastní pruh.
        if ($disclaimer && ! $columns && ! $results) {
            $blocks[] = ['type' => 'text', 'data' => [
                'body' => '<p>'.e($disclaimer).'</p>',
            ]];
        }

        if (filled($case->quote ?? null)) {
            $blocks[] = ['type' => 'quote', 'data' => array_filter([
                'text' => $case->quote,
                'author' => $case->quote_author ?? null,
            ], fn ($value) => filled($value))];
        }

        return $blocks;
    }

    /** @return array<int, mixed> */
    private function decode(?string $json): array
    {
        return $json ? (json_decode($json, true) ?: []) : [];
    }
};
