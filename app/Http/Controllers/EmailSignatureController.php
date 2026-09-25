<?php

namespace App\Http\Controllers;

use App\Models\Founder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Náhled e-mailového podpisu, odkud se zkopíruje do poštovního klienta.
 *
 * Běží jen lokálně (viz routes/web.php). Obrázky v podpisu ale musí mířit
 * na ostrý web: podpis se vloží do Gmailu nebo Outlooku a adresa
 * 127.0.0.1 by příjemci nic nezobrazila. Parametr ?lokalni-obrazky=1
 * přepne obrázky na lokální jen pro kontrolu vzhledu před nasazením.
 */
class EmailSignatureController extends Controller
{
    private const PRODUCTION_URL = 'https://taveo.cz';

    public function __invoke(Request $request): View
    {
        $usesLocalImages = $request->boolean('lokalni-obrazky');
        $imageBase = $usesLocalImages
            ? asset('images/email')
            : self::PRODUCTION_URL.'/images/email';

        return view('email-signature.show', [
            'usesLocalImages' => $usesLocalImages,
            'founders' => Founder::callable()->ordered()->get(),
            'websiteUrl' => self::PRODUCTION_URL,
            'websiteLabel' => parse_url(self::PRODUCTION_URL, PHP_URL_HOST),
            'logoUrl' => $imageBase.'/podpis-logo.png',
            'photoUrl' => $imageBase.'/podpis-foto.png',
        ]);
    }
}
