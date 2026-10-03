<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Support\ClientDashboard;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Přehled spolupráce pro klienta. Odkaz chrání náhodný token, stránka se
 * nesmí indexovat (noindex v layoutu a robots.txt).
 */
class ClientDashboardController extends Controller
{
    public function __invoke(Request $request, string $token): View
    {
        // Přihlášený správce otevře i vypnutý přehled, aby ho před sdílením zkontroloval.
        $client = Client::query()
            ->where('dashboard_token', $token)
            ->when($request->user() === null, fn ($query) => $query->where('dashboard_enabled', true))
            ->with(['retainers', 'months'])
            ->firstOrFail();

        $dashboard = ClientDashboard::for($client, $request->query('mesic'));

        return view('client-dashboard.show', [
            'client' => $client,
            'dashboard' => $dashboard,
            'retainers' => $dashboard->retainers(),
            'work' => $dashboard->work(),
            'waiting' => $dashboard->waiting(),
            'plan' => $dashboard->plan(),
            'history' => $dashboard->history(),
            'legend' => $dashboard->legend(),
            'documents' => $dashboard->documents(),
            'summary' => $dashboard->summary(),
            'months' => array_map(fn (array $month): array => $month + [
                'url' => route('client-dashboard.show', [$token, 'mesic' => $month['key']]),
            ], $dashboard->months()),
            'isDraft' => ! $client->dashboard_enabled,
        ]);
    }
}
