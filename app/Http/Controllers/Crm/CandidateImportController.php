<?php

namespace App\Http\Controllers\Crm;

use App\Enums\Crm\CompanySegment;
use App\Enums\Crm\CompanySource;
use App\Enums\Crm\CompanyStatus;
use App\Http\Controllers\Controller;
use App\Models\Crm\Company;
use App\Support\Crm\Domain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Příjem firem k proklepnutí z externí automatizace (třeba naplánovaný
 * agent, který hledá e-shopy). Firmy se jen založí jako nové, posoudí je
 * ranní `crm:scout --unscored --park`. Tak endpoint odpoví hned, i když
 * přijde padesát webů.
 */
class CandidateImportController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'companies' => ['required', 'array', 'max:200'],
            'companies.*.website' => ['required', 'string', 'max:255'],
            'companies.*.name' => ['nullable', 'string', 'max:255'],
            'companies.*.city' => ['nullable', 'string', 'max:255'],
            'companies.*.segment' => ['nullable', 'string', 'max:30'],
            'companies.*.note' => ['nullable', 'string', 'max:2000'],
        ]);

        $known = Company::withTrashed()->whereNotNull('domain')->pluck('domain')->flip();
        $created = 0;
        $skipped = [];

        foreach ($validated['companies'] as $row) {
            $domain = Domain::normalize($row['website']);

            if ($domain === null || $known->has($domain)) {
                $skipped[] = $row['website'];

                continue;
            }

            Company::create([
                'name' => $row['name'] ?? Str::before($domain, '.'),
                'website' => $row['website'],
                'city' => $row['city'] ?? null,
                'segment' => CompanySegment::tryFrom((string) ($row['segment'] ?? '')) ?? CompanySegment::Eshop,
                'source' => CompanySource::Research,
                'status' => CompanyStatus::New,
                'notes' => $row['note'] ?? null,
            ]);

            $known[$domain] = true;
            $created++;
        }

        return response()->json(['created' => $created, 'skipped' => count($skipped), 'skipped_websites' => $skipped]);
    }
}
