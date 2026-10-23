<?php

namespace App\Http\Controllers;

use App\Jobs\StartAgentJob;
use App\Support\ClickUp\ClickUp;
use App\Support\ClickUp\ClickUpAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Webhook ClickUpu (nový komentář). Ověří podpis, zapíše úlohu a odpoví hned.
 * Úkol z ClickUpu dohledá a agenta spustí až po odeslání odpovědi, ClickUp
 * na pomalý webhook nečeká a posílá ho znovu.
 */
class ClickUpWebhookController extends Controller
{
    public function __invoke(Request $request, ClickUpAgent $agent): JsonResponse
    {
        abort_unless(ClickUp::validSignature($request->getContent(), $request->header('X-Signature')), 401);

        if ($job = $agent->accept($request->json()->all())) {
            StartAgentJob::dispatchAfterResponse($job->getKey());
        }

        return response()->json(['ok' => true]);
    }
}
