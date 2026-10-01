<?php

namespace App\Rules;

use App\Models\EshopOffer;
use App\Models\Page;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

/**
 * Adresu /{slug} sdílí statické stránky s nabídkami pro e-shopy a před nimi
 * ještě pevné routy. Slug proto nesmí být obsazený ani v jedné tabulce,
 * jinak by jedna stránka tiše schovala druhou.
 */
class FreeTopLevelSlug implements ValidationRule
{
    /** Jednosegmentové adresy, které obslouží routa nad catch-all. */
    private const RESERVED = ['admin', 'nastroje', 'install', 'reference', 'sluzby', 'poptavka', 'sitemap.xml', 'robots.txt', 'podpis-emailu'];

    public function __construct(private ?Model $ignore = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $slug = (string) $value;

        if (in_array($slug, self::RESERVED, true)) {
            $fail('Tahle adresa patří jiné části webu.');

            return;
        }

        foreach ([Page::class => 'Statická stránka', EshopOffer::class => 'Nabídka pro e-shopy'] as $model => $label) {
            $taken = $model::where('slug', $slug)
                ->when($this->ignore instanceof $model, fn ($query) => $query->whereKeyNot($this->ignore->getKey()))
                ->exists();

            if ($taken) {
                $fail("Adresu už používá {$label}.");

                return;
            }
        }
    }
}
