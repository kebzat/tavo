<?php

namespace App\Models;

use App\Models\Concerns\HasContentBlocks;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasContentBlocks;

    protected $guarded = [];

    protected $casts = [
        'published' => 'boolean',
        'hero_cta' => 'boolean',
        'blocks' => 'array',
    ];

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('published', true);
    }

    /**
     * Nadpis rozsekaný na části kolem zvýrazněného výrazu, aby ho šablona mohla
     * vysázet cihlovou kurzívou. Když `hero_accent` v nadpisu není, vrátí se celý
     * nadpis v jednom kuse a zvýraznění se prostě nekoná.
     *
     * @return array{before: string, accent: ?string, after: string}
     */
    public function headlineParts(): array
    {
        $title = (string) $this->title;
        $accent = trim((string) $this->hero_accent);

        if ($accent === '' || ! str_contains($title, $accent)) {
            return ['before' => $title, 'accent' => null, 'after' => ''];
        }

        $at = mb_strpos($title, $accent);

        return [
            'before' => mb_substr($title, 0, $at),
            'accent' => $accent,
            'after' => mb_substr($title, $at + mb_strlen($accent)),
        ];
    }
}
