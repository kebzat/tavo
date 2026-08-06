<?php

namespace App\Http\Controllers;

use App\Models\Checklist;
use App\Models\ChecklistItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Odškrtnutí položky ze sdílené stránky.
 *
 * Kdo zná odkaz, může odškrtávat. Je to vědomé rozhodnutí: checklist je
 * pracovní podklad, ne účetnictví, a přihlašování by ho pro klienta zabilo.
 * Proti překlepům chrání to, že přepnutí je vratné jedním klikem.
 */
class ChecklistToggleController extends Controller
{
    public function __invoke(Request $request, string $token, ChecklistItem $item): JsonResponse|RedirectResponse
    {
        $checklist = Checklist::query()
            ->where('public_token', $token)
            ->where('is_public', true)
            ->where('is_template', false)
            ->firstOrFail();

        // Bez tohohle by šlo cizím tokenem přepnout položku odjinud.
        abort_unless($item->checklist_id === $checklist->getKey(), 404);

        $status = $item->toggleDone();

        $category = $item->section->category;

        if (! $request->expectsJson()) {
            return back();
        }

        return response()->json([
            'done' => $status->isFinished(),
            'stav' => $status->getLabel(),
            'kategorie' => $category->progress(),
            'celkem' => $checklist->progress(),
        ]);
    }
}
