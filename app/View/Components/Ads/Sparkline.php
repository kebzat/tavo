<?php

namespace App\View\Components\Ads;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/** Malá čára trendu do karty klienta. Bez os a popisků, jen tvar. */
class Sparkline extends Component
{
    public string $points = '';

    /** @param  list<float>  $values */
    public function __construct(public array $values, public string $class = 'text-primary-500')
    {
        $count = count($values);
        $peak = $count ? max($values) : 0;

        if ($count < 2 || $peak <= 0) {
            return;
        }

        $this->points = collect($values)
            ->map(fn (float $value, int $i): string => round($i / ($count - 1) * 100, 2).','.round(28 - $value / $peak * 26, 2))
            ->implode(' ');
    }

    public function render(): View
    {
        return view('components.ads.sparkline');
    }
}
