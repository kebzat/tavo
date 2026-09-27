<?php

use App\Enums\Crm\FitVerdict;
use App\Models\Crm\Company;
use App\Support\Crm\Scout\FitScorer;
use App\Support\Crm\Scout\ProspectScout;
use Illuminate\Database\Migrations\Migration;

/**
 * Hledáme jen e-shopy (rozhodnutí Toma z 27. 9. 2026). Verdikt už
 * proklepnutých firem se přepočítá z uložených měření, web se znovu
 * nestahuje. Agentury, služby a weby bez e-shopu z rešerše se odloží,
 * stejně jako by je odložil ranní `crm:scout --park`.
 *
 * Odkládá jen nové firmy z rešerše (viz ProspectScout::parkIfRejected),
 * rozjednaných ani poptávek se nedotkne. Vrátit jde změnou stavu.
 */
return new class extends Migration
{
    public function up(): void
    {
        $scout = app(ProspectScout::class);

        Company::query()->whereNotNull('scout_data')->each(function (Company $company) use ($scout): void {
            $data = $company->scout_data;
            $fit = FitScorer::score($data['measurements'] ?? [], $data['findings'] ?? [], $company->segment);

            $verdict = $fit['verdict'];
            $score = $fit['score'];

            // Úsudek Clauda z dřívějška platí dál, jen pro e-shopy.
            if ($verdict !== FitVerdict::Poor && isset($data['ai']['adjustment'])) {
                $score = max(0, min(100, $score + (int) $data['ai']['adjustment']));
                $verdict = FitVerdict::fromScore($score);
            }

            $data['reasons'] = $fit['reasons'];
            $data['base_score'] = $fit['score'];

            $company->forceFill([
                'fit_score' => $score,
                'fit_verdict' => $verdict,
                'scout_data' => $data,
            ])->save();

            $scout->parkIfRejected($company);
        });
    }

    public function down(): void
    {
        // Odložené firmy se vracejí ručně změnou stavu.
    }
};
