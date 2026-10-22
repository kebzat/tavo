<?php

namespace App\Http\Controllers;

use App\Models\Questionnaire;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Dotazník na sdíleném odkazu. Kdo zná adresu, může odpovídat, stejně jako
 * u checklistu. S JS se odpovědi ukládají samy na pozadí, bez JS celé
 * najednou tlačítkem.
 */
class QuestionnaireController extends Controller
{
    public function show(Request $request, string $slug): View
    {
        $questionnaire = $this->find($request, $slug);

        return view('questionnaire.show', [
            'questionnaire' => $questionnaire,
            'sections' => $questionnaire->sectionItems(),
            'progress' => $questionnaire->progress(),
        ]);
    }

    public function save(Request $request, string $slug): JsonResponse|RedirectResponse
    {
        $questionnaire = $this->find($request, $slug);

        $request->validate(['answers' => ['nullable', 'array']]);

        $questionnaire->fillAnswers($request->input('answers', []));

        if ($request->boolean('odeslat')) {
            $questionnaire->submitted_at ??= now();
        }

        $questionnaire->save();

        if (! $request->expectsJson()) {
            return redirect()->route('questionnaire.show', $questionnaire->slug);
        }

        return response()->json([
            'odeslano' => $questionnaire->submitted_at !== null,
        ] + $questionnaire->progress());
    }

    /** Přihlášený správce otevře i nesdílený dotazník, aby ho mohl zkontrolovat. */
    private function find(Request $request, string $slug): Questionnaire
    {
        return Questionnaire::query()
            ->when($request->user() === null, fn ($query) => $query->public())
            ->where('slug', $slug)
            ->firstOrFail();
    }
}
