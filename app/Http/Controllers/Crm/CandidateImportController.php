<?php

namespace App\Http\Controllers\Crm;

use App\Enums\Crm\CompanySegment;
use App\Enums\Crm\CompanySource;
use App\Enums\Crm\CompanyStatus;
use App\Http\Controllers\Controller;
use App\Jobs\CompletePageSpeed;
use App\Models\Crm\Company;
use App\Support\Crm\Domain;
use App\Support\Crm\Scout\ProspectScout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Příjem firem k proklepnutí z externí automatizace (Claude Code, agent,
 * který hledá e-shopy).
 *
 * Bez měření se firma jen založí a posoudí ji ranní
 * `crm:scout --unscored --park`. S měřením z `crm:measure` (a případně
 * s posudkem) se skóre spočítá hned a Claude API se neptá. PageSpeed,
 * který v měření chybí, doměří server po odeslání odpovědi.
 *
 * Firma, kterou jsme dřív smazali, se obnoví jako nová.
 */
class CandidateImportController extends Controller
{
    public function __invoke(Request $request, ProspectScout $scout): JsonResponse
    {
        $validated = $request->validate([
            'companies' => ['required', 'array', 'max:200'],
            'companies.*.website' => ['required', 'string', 'max:255'],
            'companies.*.name' => ['nullable', 'string', 'max:255'],
            'companies.*.city' => ['nullable', 'string', 'max:255'],
            'companies.*.segment' => ['nullable', 'string', 'max:30'],
            'companies.*.note' => ['nullable', 'string', 'max:2000'],
            'companies.*.measurements' => ['nullable', 'array'],
            'companies.*.assessment' => ['nullable', 'array'],
            'companies.*.assessment.summary' => ['nullable', 'string', 'max:500'],
            'companies.*.assessment.adjustment' => ['nullable', 'integer'],
            'companies.*.assessment.note' => ['nullable', 'string', 'max:1000'],
            'companies.*.assessment.hook' => ['nullable', 'string', 'max:500'],
        ]);

        $created = 0;
        $restored = 0;
        $updated = 0;
        $skipped = [];
        $missingPageSpeed = [];

        foreach ($validated['companies'] as $row) {
            $domain = Domain::normalize($row['website']);

            if ($domain === null) {
                $skipped[] = $row['website'];

                continue;
            }

            $company = Company::withTrashed()->where('domain', $domain)->first();
            $measurements = $row['measurements'] ?? null;

            if ($company?->trashed()) {
                $company->restore();
                $company->forceFill([
                    'status' => CompanyStatus::New,
                    'source' => CompanySource::Research,
                    'fit_score' => null,
                    'fit_verdict' => null,
                    'scout_data' => null,
                    'scouted_at' => null,
                ])->save();
                $restored++;
            } elseif ($company !== null) {
                if ($measurements === null) {
                    $skipped[] = $row['website'];

                    continue;
                }
                $updated++;
            } else {
                $company = Company::create([
                    'name' => $row['name'] ?? Str::before($domain, '.'),
                    'website' => $row['website'],
                    'city' => $row['city'] ?? null,
                    'segment' => CompanySegment::tryFrom((string) ($row['segment'] ?? '')) ?? CompanySegment::Eshop,
                    'source' => CompanySource::Research,
                    'status' => CompanyStatus::New,
                    'notes' => $row['note'] ?? null,
                ]);
                $created++;
            }

            if ($measurements !== null) {
                $scout->record($company, $measurements, $this->assessment($row['assessment'] ?? null), askAi: false);
                $scout->parkIfRejected($company);

                if (($measurements['reachable'] ?? false) && ($measurements['pagespeed'] ?? null) === null) {
                    $missingPageSpeed[] = $company->getKey();
                }
            }
        }

        if ($missingPageSpeed !== []) {
            CompletePageSpeed::dispatchAfterResponse($missingPageSpeed);
        }

        return response()->json([
            'created' => $created,
            'restored' => $restored,
            'updated' => $updated,
            'skipped' => count($skipped),
            'skipped_websites' => $skipped,
            'pagespeed_pending' => count($missingPageSpeed),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $row
     * @return array{summary: string, adjustment: int, note: string, hook: string}|null
     */
    private function assessment(?array $row): ?array
    {
        if (blank($row['summary'] ?? null) && blank($row['note'] ?? null)) {
            return null;
        }

        return [
            'summary' => (string) ($row['summary'] ?? ''),
            'adjustment' => (int) ($row['adjustment'] ?? 0),
            'note' => (string) ($row['note'] ?? ''),
            'hook' => str_replace([' — ', '—'], [', ', ', '], (string) ($row['hook'] ?? '')),
        ];
    }
}
