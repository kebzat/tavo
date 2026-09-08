<?php

namespace App\Models;

use App\Support\WebTexts;
use Illuminate\Database\Eloquent\Model;

/**
 * Jeden statický text webu. Vzniká sám při prvním vykreslení šablony,
 * viz App\Support\WebTexts.
 */
class WebText extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        // Texty se čtou na každé stránce, takže leží v cache. Po každé změně
        // v administraci ji musíme zahodit, jinak by se úprava projevila
        // až po vypršení nebo ručním vyčištění.
        static::saved(fn () => WebTexts::forget());
        static::deleted(fn () => WebTexts::forget());
    }
}
