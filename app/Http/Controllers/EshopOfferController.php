<?php

namespace App\Http\Controllers;

use App\Models\EshopOffer;
use App\Support\StructuredData;
use Illuminate\Contracts\View\View;

/**
 * Dopadové stránky s nabídkami pro e-shopy. Obsah se edituje v administraci
 * (Obsah → Nabídky pro e-shopy), na stránku je posílá PageController.
 */
class EshopOfferController extends Controller
{
    public function show(EshopOffer $offer): View
    {
        $faq = $offer->faqItems();

        return view('eshop.show', [
            'offer' => $offer,
            'intro' => $offer->introParagraphs(),
            'sections' => $offer->contentSections(),
            'faq' => $faq,
            'others' => EshopOffer::published()->ordered()->whereKeyNot($offer->id)->get(),
            'schema' => array_values(array_filter([
                StructuredData::eshopOffer($offer),
                $faq ? StructuredData::faq($faq) : null,
                StructuredData::breadcrumbs([
                    'Úvod' => route('home'),
                    $offer->nav_label => $offer->url(),
                ]),
            ])),
        ]);
    }
}
