<?php

namespace App\Http\Controllers;

use App\Support\EshopOffers;
use App\Support\StructuredData;
use Illuminate\Contracts\View\View;

/**
 * Dopadové stránky s nabídkami pro e-shopy. Obsah drží App\Support\EshopOffers,
 * routy se z něj generují v routes/web.php.
 */
class EshopOfferController extends Controller
{
    public function __invoke(string $slug): View
    {
        $offer = EshopOffers::find($slug);

        return view('eshop.show', [
            'offer' => $offer,
            'others' => EshopOffers::others($slug),
            'schema' => [
                StructuredData::eshopOffer($offer),
                StructuredData::faq($offer['faq']),
                StructuredData::breadcrumbs([
                    'Úvod' => route('home'),
                    $offer['nav_label'] => $offer['url'],
                ]),
            ],
        ]);
    }
}
