<?php

namespace App\Http\Controllers\Crm;

use App\Filament\Tools\Resources\Companies\Pages\EditCompany;
use App\Http\Controllers\Controller;
use App\Models\Crm\Company;
use App\Support\Crm\Domain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Skóre a měření firmy pro Claude Code. Skill /audit-eshopu si odsud
 * bere PageSpeed, který lokálně změřit nejde, a hromadné hledání tu
 * ověří, jak server firmu nakonec posoudil.
 */
class CompanyScoutController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['website' => ['required', 'string', 'max:255']]);

        $company = Company::where('domain', Domain::normalize($request->string('website')))->first();
        abort_if($company === null, 404, 'Firma v CRM není.');

        return response()->json([
            'company' => $company->getKey(),
            'name' => $company->name,
            'status' => $company->status?->value,
            'fit_score' => $company->fit_score,
            'fit_verdict' => $company->fit_verdict?->value,
            'scouted_at' => $company->scouted_at?->toIso8601String(),
            'scout' => $company->scout_data,
            'edit_url' => EditCompany::getUrl(['record' => $company], panel: 'tools'),
        ]);
    }
}
