<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Support\ClientDashboard;
use App\Support\RetainerSchedule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Přehled spolupráce pro klienta. Odkaz chrání náhodná část adresy, stránka
 * se nesmí indexovat (noindex v layoutu a robots.txt). Starý token přesměruje
 * na čitelnou adresu.
 */
class ClientDashboardController extends Controller
{
    public function __invoke(Request $request, string $token): View|RedirectResponse
    {
        // Přihlášený správce otevře i vypnutý přehled, aby ho před sdílením zkontroloval.
        $client = Client::query()
            ->dashboardAt($token)
            ->when($request->user() === null, fn ($query) => $query->where('dashboard_enabled', true))
            ->with(['retainers', 'months'])
            ->firstOrFail();

        if ($token !== $client->dashboardKey()) {
            return redirect()->to($client->dashboardPreviewUrl($request->query()), 301);
        }

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
            // Plán ceny jen když ho u klienta zapneme.
            'pricing' => $client->dashboard_shows_pricing ? RetainerSchedule::for($client) : [],
            'months' => $this->withUrls($dashboard->months(), $client),
            'monthsUrl' => $client->dashboardPreviewUrl(),
            'isDraft' => ! $client->dashboard_enabled,
        ]);
    }

    /**
     * Odkaz ke každé záložce měsíce.
     *
     * @param  array<string, mixed>  $months
     * @return array<string, mixed>
     */
    private function withUrls(array $months, Client $client): array
    {
        $link = fn (array $month): array => $month + ['url' => $client->dashboardPreviewUrl(['mesic' => $month['key']])];

        return ['recent' => array_map($link, $months['recent']), 'older' => array_map($link, $months['older'])] + $months;
    }
}
