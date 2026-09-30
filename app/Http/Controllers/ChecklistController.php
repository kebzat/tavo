<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\Checklist;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Sdílený checklist klienta. Odkaz chrání jen náhodný token, takže
 * interní poznámky se sem ani nenačítají, nestačí je schovat v šabloně.
 */
class ChecklistController extends Controller
{
    /** Rozcestník s kartami kategorií. */
    public function show(string $key): View|RedirectResponse
    {
        $checklist = $this->najdi($key);

        // Starý odkaz s tokenem přesměrujeme na čitelnou adresu.
        if ($key !== $checklist->shareKey()) {
            return redirect()->route('checklist.show', $checklist->shareKey(), 301);
        }

        return view('checklist.show', [
            'checklist' => $checklist,
            'progress' => $checklist->progress(),
            'links' => $this->auditLinks($checklist),
        ]);
    }

    /** Jedna kategorie: tabulka položek rozdělená sekcemi. */
    public function category(string $key, string $slug): View|RedirectResponse
    {
        $checklist = $this->najdi($key);

        if ($key !== $checklist->shareKey()) {
            return redirect()->route('checklist.category', [$checklist->shareKey(), $slug], 301);
        }

        $category = $checklist->categories->firstWhere('slug', $slug)
            ?? abort(404);

        return view('checklist.category', [
            'checklist' => $checklist,
            'category' => $category,
            'progress' => $category->progress(),
            'links' => $this->auditLinks($checklist),
        ]);
    }

    /**
     * Sdílené audity téhož klienta jako tlačítka v hlavičce. Nejnovější první.
     *
     * @return list<array{label: string, url: string}>
     */
    private function auditLinks(Checklist $checklist): array
    {
        if (! $checklist->client) {
            return [];
        }

        return $checklist->client->audits()
            ->public()
            ->orderByDesc('audited_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Audit $audit): array => [
                'label' => $audit->audited_at
                    ? 'Přečíst audit z '.$audit->audited_at->format('j. n. Y')
                    : $audit->title,
                'url' => $audit->publicUrl(),
            ])
            ->all();
    }

    /**
     * Načte checklist i s celou strukturou. Sloupec internal_note ve výběru
     * schválně chybí, do pohledu se tedy nemá jak dostat.
     */
    private function najdi(string $key): Checklist
    {
        return Checklist::query()
            ->sharedAs($key)
            ->where('is_public', true)
            ->where('is_template', false)
            ->with([
                'client',
                'categories' => fn ($query) => $query->ordered(),
                'categories.sections' => fn ($query) => $query->ordered(),
                'categories.sections.items' => fn ($query) => $query
                    ->ordered()
                    ->select(['id', 'checklist_section_id', 'title', 'description', 'priority', 'status', 'order_column']),
            ])
            ->firstOrFail();
    }
}
