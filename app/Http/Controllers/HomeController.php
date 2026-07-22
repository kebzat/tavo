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
        return view('home', [
            'home' => $home,
            'services' => Service::published()->ordered()->get(),
            'cases' => CaseStudy::published()->where('is_featured', true)->ordered()->with('category')->get(),
            'loopItems' => $home->loop_items,
            'processSteps' => ProcessStep::ordered()->get(),
            'founders' => Founder::ordered()->get(),
        ]);
    }
}
