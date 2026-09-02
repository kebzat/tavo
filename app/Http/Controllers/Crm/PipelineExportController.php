<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Activity;
use App\Models\Crm\Company;
use App\Models\Crm\Deal;
use Illuminate\Http\JsonResponse;

/**
 * Odchozí obraz pipeline pro externí vyhodnocení.
 *
 * Jen ke čtení a jen to podstatné — firmy, obchody a aktivity za posledních
 * třicet dní. Delší historie by z endpointu udělala zálohu databáze, což není
 * jeho úkol.
 */
class PipelineExportController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $since = now()->subDays(30);

        return response()->json([
            'generated_at' => now()->toAtomString(),
            'since' => $since->toDateString(),

            'companies' => Company::query()
                ->with('owner:id,name')
                ->get()
                ->map(fn (Company $company): array => [
                    'id' => $company->getKey(),
                    'name' => $company->name,
                    'domain' => $company->domain,
                    'city' => $company->city,
                    'segment' => $company->segment->value,
                    'status' => $company->status->value,
                    'priority' => $company->priority->value,
                    'source' => $company->source->value,
                    'owner' => $company->owner?->name,
                    'next_action_at' => $company->next_action_at?->toAtomString(),
                    'last_activity_at' => $company->last_activity_at?->toAtomString(),
                ]),

            'deals' => Deal::query()
                ->with('owner:id,name')
                ->get()
                ->map(fn (Deal $deal): array => [
                    'id' => $deal->getKey(),
                    'company_id' => $deal->company_id,
                    'title' => $deal->title,
                    'package' => $deal->package->value,
                    'stage' => $deal->stage->value,
                    'value_czk' => $deal->value_czk,
                    'probability' => $deal->probability,
                    'weighted_value_czk' => (int) round($deal->weightedValue()),
                    'owner' => $deal->owner?->name,
                    'expected_close_at' => $deal->expected_close_at?->toDateString(),
                    'won_at' => $deal->won_at?->toAtomString(),
                    'lost_at' => $deal->lost_at?->toAtomString(),
                ]),

            'activities' => Activity::query()
                ->where('happened_at', '>=', $since)
                ->with('user:id,name')
                ->orderBy('happened_at')
                ->get()
                ->map(fn (Activity $activity): array => [
                    'id' => $activity->getKey(),
                    'company_id' => $activity->company_id,
                    'deal_id' => $activity->deal_id,
                    'type' => $activity->type->value,
                    'subject' => $activity->subject,
                    'outcome' => $activity->outcome?->value,
                    'happened_at' => $activity->happened_at->toAtomString(),
                    'follow_up_at' => $activity->follow_up_at?->toAtomString(),
                    'user' => $activity->user?->name,
                ]),
        ]);
    }
}
