<?php

namespace App\Http\Controllers;

use App\Models\EshopOffer;
use App\Models\Page;
use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    /**
     * Volný slug na nejvyšší úrovni. Patří buď nabídce pro e-shopy, nebo
     * statické stránce; formuláře v administraci hlídají, aby se nepotkaly.
     */
    public function show(string $slug): View
    {
        $offer = EshopOffer::published()->where('slug', $slug)->first();

        if ($offer) {
            return app(EshopOfferController::class)->show($offer);
        }

        $page = Page::published()->where('slug', $slug)->firstOrFail();

        return view('pages.show', [
            'page' => $page,
            'blocks' => $page->contentBlocks(),
            'headline' => $page->headlineParts(),
        ]);
    }
}
