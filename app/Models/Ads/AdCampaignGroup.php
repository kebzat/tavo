<?php

namespace App\Models\Ads;

use App\Enums\Ads\PrimaryGoal;
use App\Models\Client;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Skupina kampaní klienta: „Prodej“, „Brand“, „Nábor“. Má vlastní cíl,
 * rozpočet, cílovou cenu a ROAS, výběr čísel a prahy upozornění. Detail,
 * graf, upozornění i report jdou počítat jen za ni. Celková útrata účtu
 * zůstává vidět zvlášť („Celý účet“).
 */
class AdCampaignGroup extends Model
{
    protected $guarded = [];

    protected $attributes = [
        'goal' => 'purchases',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(AdCampaign::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('name');
    }

    /** @return list<int> ID kampaní ve skupině */
    public function campaignIds(): array
    {
        return $this->campaigns()->pluck('id')->all();
    }

    /**
     * Slova z pravidla „název obsahuje“, malými písmeny a bez diakritiky.
     *
     * @return list<string>
     */
    public function ruleWords(): array
    {
        return collect(explode(',', (string) $this->name_rule))
            ->map(fn (string $word): string => self::normalize($word))
            ->filter()
            ->values()
            ->all();
    }

    /** Odpovídá název kampaně pravidlu skupiny? */
    public function matchesName(string $campaignName): bool
    {
        $name = self::normalize($campaignName);

        foreach ($this->ruleWords() as $word) {
            if (str_contains($name, $word)) {
                return true;
            }
        }

        return false;
    }

    public static function normalize(string $text): string
    {
        return trim(Str::lower(Str::ascii($text)));
    }

    protected function casts(): array
    {
        return [
            'goal' => PrimaryGoal::class,
            'monthly_budget' => 'float',
            'target_cpa' => 'float',
            'target_roas' => 'float',
            'dashboard' => 'array',
            'alert_overrides' => 'array',
            'weekly_report' => 'boolean',
            'monthly_report' => 'boolean',
        ];
    }
}
