<?php

namespace App\Http\Controllers;

use App\Models\Checklist;
use Illuminate\Contracts\View\View;

/**
 * Sdílený checklist klienta. Odkaz chrání jen náhodný token, takže
 * interní poznámky se sem ani nenačítají, nestačí je schovat v šabloně.
 */
class ChecklistController extends Controller
{
    /** Rozcestník s kartami kategorií. */
    public function show(string $token): View
    {
        $checklist = $this->najdi($token);

        return view('checklist.show', [
            'checklist' => $checklist,
            'progress' => $checklist->progress(),
        ]);
    }

    /** Jedna kategorie: tabulka položek rozdělená sekcemi. */
    public function category(string $token, string $slug): View
    {
        $checklist = $this->najdi($token);

        $category = $checklist->categories->firstWhere('slug', $slug)
            ?? abort(404);

        return view('checklist.category', [
            'checklist' => $checklist,
            'category' => $category,
            'progress' => $category->progress(),
        ]);
    }

    /**
     * Načte checklist i s celou strukturou. Sloupec internal_note ve výběru
     * schválně chybí, do pohledu se tedy nemá jak dostat.
     */
    private function najdi(string $token): Checklist
    {
        return Checklist::query()
            ->where('public_token', $token)
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
