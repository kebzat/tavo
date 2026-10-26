<?php

namespace App\View\Components\Ads;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Denní graf: sloupce útraty a přes ně čára konverzí, každá řada se svým
 * měřítkem. Kreslí se na serveru do SVG, takže funguje v panelu, na sdílené
 * stránce i při tisku do PDF bez knihovny na grafy. Tooltip řeší Alpine.
 *
 * Vstup je tvar z PerformanceView::chart(). Den bez stažených dat
 * („missing“) se nekreslí jako nula, ale jako šedé šrafování a čára se
 * v něm přeruší. Dny, které Meta ještě dopočítává („settling“), jsou
 * světlejší. Značky nad sloupci jsou zásahy a termíny z kalendáře.
 */
class Chart extends Component
{
    /** Šířka viewBoxu. Výška je 100, SVG se roztáhne na rozměr kontejneru. */
    private const WIDTH = 1000;

    /** Kolik popisků dnů se nejvýš vejde pod graf. */
    private const MAX_LABELS = 8;

    /** @var list<array{x: float, width: float, height: float, label: string, bar: string, line: string, state: string, marks: list<array{kind: string, mark: string, text: string}>}> */
    public array $columns = [];

    public string $linePath = '';

    /** @var list<string> Úseky čáry mezi chybějícími dny. */
    public array $lineSegments = [];

    public bool $hasMissing = false;

    public bool $hasSettling = false;

    public bool $hasMarkers = false;

    /** @var list<int> */
    public array $labelIndexes = [];

    public ?string $barMax = null;

    public ?string $lineMax = null;

    /**
     * @param  list<string>  $labels
     * @param  array{label: string, values: list<float>, display: list<string>}  $bars
     * @param  array{label: string, values: list<float>, display: list<string>}  $line
     * @param  list<string>  $states  ok / settling / missing po dnech
     * @param  list<array{index: int, kind: string, mark: string, text: string}>  $markers
     */
    public function __construct(
        public array $labels,
        public array $bars,
        public array $line,
        public int $height = 240,
        public string $lineClass = 'text-gray-900 dark:text-white',
        public array $states = [],
        public array $markers = [],
    ) {
        $count = count($labels);

        if ($count === 0) {
            return;
        }

        $barValues = $bars['values'];
        $lineValues = $line['values'];
        $barPeak = max($barValues) ?: 0;
        $linePeak = max($lineValues) ?: 0;
        $band = self::WIDTH / $count;

        $marks = collect($markers)->groupBy('index');

        foreach ($labels as $i => $label) {
            $state = $states[$i] ?? 'ok';
            $this->hasMissing = $this->hasMissing || $state === 'missing';
            $this->hasSettling = $this->hasSettling || $state === 'settling';

            $this->columns[] = [
                'x' => round($i * $band + $band * 0.18, 2),
                'width' => round($band * 0.64, 2),
                // Nahoře necháme 8 % místa, ať nejvyšší sloupec nenaráží na okraj.
                'height' => $barPeak > 0 && $state !== 'missing' ? round($barValues[$i] / $barPeak * 92, 2) : 0,
                'label' => $label,
                'bar' => $state === 'missing' ? 'chybí data' : ($bars['display'][$i] ?? ''),
                'line' => $state === 'missing' ? 'chybí data' : ($line['display'][$i] ?? ''),
                'state' => $state,
                'marks' => ($marks[$i] ?? collect())->map(fn (array $mark): array => ['kind' => $mark['kind'], 'mark' => $mark['mark'], 'text' => $mark['text']])->values()->all(),
            ];
        }

        $this->hasMarkers = $markers !== [];

        if ($linePeak > 0) {
            // Čára se v chybějících dnech přeruší, nula by tam lhala.
            $segment = [];

            foreach ($lineValues as $i => $value) {
                if (($states[$i] ?? 'ok') !== 'missing') {
                    $segment[] = round($i * $band + $band / 2, 2).','.round(100 - $value / $linePeak * 92, 2);

                    continue;
                }

                if ($segment !== []) {
                    $this->lineSegments[] = implode(' ', $segment);
                }

                $segment = [];
            }

            if ($segment !== []) {
                $this->lineSegments[] = implode(' ', $segment);
            }

            $this->linePath = implode(' ', $this->lineSegments);
            $this->lineMax = $line['display'][array_search($linePeak, $lineValues, true)] ?? null;
        }

        if ($barPeak > 0) {
            $this->barMax = $bars['display'][array_search($barPeak, $barValues, true)] ?? null;
        }

        $step = (int) ceil($count / self::MAX_LABELS);
        $this->labelIndexes = array_values(array_filter(array_keys($labels), fn (int $i): bool => $i % $step === 0));
    }

    public function viewBox(): string
    {
        return '0 0 '.self::WIDTH.' 100';
    }

    public function render(): View
    {
        return view('components.ads.chart');
    }
}
