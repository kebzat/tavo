<?php

namespace App\Http\Controllers;

use App\Models\CaseStudy;
use App\Models\Founder;
use App\Models\ProcessStep;
use App\Models\Service;
use App\Settings\HomeSettings;
use Illuminate\Contracts\View\View;

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
}
