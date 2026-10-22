<?php

namespace App\Models\Marketing;

use App\Models\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Ranní přehled klienta od agenta: co se změnilo, jestli jsou data
 * spolehlivá, co hoří a navržený další krok ke schválení. Jeden na den.
 */
class ClientBriefing extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'sources' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function bodyHtml(): HtmlString
    {
        return new HtmlString(Str::markdown($this->body, ['html_input' => 'escape', 'allow_unsafe_links' => false]));
    }
}
