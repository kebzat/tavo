<?php

namespace App\Http\Controllers;

use App\Support\Marketing\MarketingGuide;
use Illuminate\Contracts\View\View;

/** Průvodce pro Pavla: co z dotazníku je v nástrojích a kde to najde. */
class MarketingGuideController extends Controller
{
    public function __invoke(): View
    {
        return view('guide.marketing', ['guide' => MarketingGuide::content()]);
    }
}
