<?php

namespace App\Http\Controllers;

use App\Models\CaseStudy;
use App\Models\Founder;
use App\Models\ProcessStep;
use App\Models\Service;
use App\Settings\HomeSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

class HomeController extends Controller
{
    public function __invoke(HomeSettings $home): View
    {
        $founders = Founder::ordered()->get();

        return view('home', [
            'home' => $home,
            'services' => Service::published()->ordered()->get(),
            'cases' => CaseStudy::published()->where('is_featured', true)->ordered()->with('category')->get(),
            'loopItems' => $home->loop_items,
            'processSteps' => ProcessStep::ordered()->get(),
            'pricingPlans' => $this->pricingPlans($home),
            'founders' => $founders,
            // Společná fotka je jedna, ale nahrává se u kteréhokoliv zakladatele —
            // vezmeme první, která existuje.
            'foundersPhoto' => $founders
                ->map(fn (Founder $founder): ?array => $founder->photoImage(
                    'Zakladatelé '.$founders->pluck('name')->join(' a '),
                ))
                ->filter()
                ->first(),
        ]);
    }

    /**
     * Karty ceníku s doplněnými klíči. Repeater ve Filamentu nevyplněná pole
     * někdy vůbec neuloží a prázdný řetězec má na webu znamenat „nic".
     * Karta bez názvu nebo ceny se vynechá celá.
     *
     * @return Collection<int, array{name: string, when: ?string, text: ?string, price: string, price_unit: ?string, price_note: ?string, highlight: bool}>
     */
    private function pricingPlans(HomeSettings $home): Collection
    {
        return collect($home->pricing_plans)
            ->map(fn (array $plan): array => [
                'name' => trim((string) ($plan['name'] ?? '')),
                'when' => filled($plan['when'] ?? null) ? $plan['when'] : null,
                'text' => filled($plan['text'] ?? null) ? $plan['text'] : null,
                'price' => trim((string) ($plan['price'] ?? '')),
                'price_unit' => filled($plan['price_unit'] ?? null) ? $plan['price_unit'] : null,
                'price_note' => filled($plan['price_note'] ?? null) ? $plan['price_note'] : null,
                'highlight' => (bool) ($plan['highlight'] ?? false),
            ])
            ->filter(fn (array $plan): bool => $plan['name'] !== '' && $plan['price'] !== '')
            ->values();
    }
}
