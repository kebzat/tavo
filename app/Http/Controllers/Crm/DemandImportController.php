<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Support\Crm\DemandImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Příjem poptávek z externí automatizace.
 *
 * Endpoint pro stroj, ne pro člověka: bez session, bez CSRF, ověřený sdíleným
 * tokenem. Ranní běh posílá celý dnešní výpis z portálů; co už máme, se podle
 * adresy jen aktualizuje.
 */
class DemandImportController extends Controller
{
    public function __invoke(Request $request, DemandImporter $importer): JsonResponse
    {
        $validated = $request->validate([
            'demands' => ['required', 'array', 'max:500'],
            'demands.*.url' => ['required', 'string', 'max:255'],
            'demands.*.title' => ['nullable', 'string', 'max:255'],
            'demands.*.source' => ['nullable', 'string', 'max:30'],
            'demands.*.summary' => ['nullable', 'string'],
            'demands.*.posted_at' => ['nullable', 'string', 'max:40'],
            'demands.*.budget_estimate' => ['nullable', 'string', 'max:255'],
            'demands.*.priority' => ['nullable', 'string', 'max:5'],
        ]);

        return response()->json($importer->import($validated['demands']));
    }
}
