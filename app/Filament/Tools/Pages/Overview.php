<?php

namespace App\Filament\Tools\Pages;

use App\Settings\CrmSettings;
use App\Support\Crm\ChannelBreakdown;
use App\Support\Crm\CsvExport;
use App\Support\Crm\WeeklyKpi;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Týdenní obchodní přehled.
 *
 * Odpovídá na jedinou otázku: děláme toho tenhle týden dost? Proto se čísla
 * ukazují vždy proti cíli, ne samostatně — dvanáct oslovení je dobrá zpráva
 * jen do chvíle, než se ví, že cíl je dvacet.
 */
class Overview extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Přehled';

    protected static ?string $title = 'Týdenní přehled';

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 15;

    protected string $view = 'filament.tools.pages.overview';

    /** Pondělí zobrazeného týdne. V adrese, ať se dá odkaz na týden poslat. */
    #[Url]
    public ?string $week = null;

    public function mount(): void
    {
        $this->week ??= Carbon::now()->startOfWeek()->toDateString();
    }

    public function kpi(): WeeklyKpi
    {
        return new WeeklyKpi(Carbon::parse($this->week));
    }

    public function previousWeek(): void
    {
        $this->week = Carbon::parse($this->week)->subWeek()->toDateString();
    }

    public function nextWeek(): void
    {
        $this->week = Carbon::parse($this->week)->addWeek()->toDateString();
    }

    public function thisWeek(): void
    {
        $this->week = Carbon::now()->startOfWeek()->toDateString();
    }

    public function isCurrentWeek(): bool
    {
        return Carbon::parse($this->week)->isSameWeek(Carbon::now());
    }

    /**
     * Řádky tabulky: kolik jsme udělali, kolik jsme chtěli a jestli to stačí.
     *
     * @return Collection<int, array{key: string, label: string, value: int, goal: ?int, met: ?bool, note: ?string}>
     */
    public function rows(): Collection
    {
        $kpi = $this->kpi();
        $metrics = $kpi->metrics();
        $goals = app(CrmSettings::class)->goals();

        return collect(WeeklyKpi::labels())->map(function (string $label, string $key) use ($metrics, $goals, $kpi): array {
            $goal = $goals[$key] ?? null;
            $value = $metrics[$key] ?? 0;

            return [
                'key' => $key,
                'label' => $label,
                'value' => $value,
                'goal' => $goal,
                // U vyhraných obchodů cíl nemáme — vyhrává se, když to vyjde,
                // ne když se snažíme víc.
                'met' => $goal !== null ? $value >= $goal : null,
                'note' => $key === 'won' && $kpi->wonValue() > 0
                    ? number_format($kpi->wonValue(), 0, ',', ' ').' Kč'
                    : null,
            ];
        })->values();
    }

    /** Osm týdnů zpět pro sloupcový graf. */
    public function chart(): Collection
    {
        return WeeklyKpi::lastWeeks();
    }

    /** Nejvyšší sloupec v grafu. Podle něj se počítají výšky ostatních. */
    public function chartMax(): int
    {
        return max(1, (int) $this->chart()->flatMap(fn (array $week): array => [
            $week['outreach'], $week['replies'], $week['proposals'],
        ])->max());
    }

    public function bySource(): Collection
    {
        return ChannelBreakdown::bySource();
    }

    public function bySegment(): Collection
    {
        return ChannelBreakdown::bySegment();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(function () {
                    $kpi = $this->kpi();

                    $rows = $this->rows()->map(fn (array $row): array => [
                        $row['label'],
                        $row['value'],
                        $row['goal'] ?? '—',
                        $row['goal'] === null ? '' : ($row['met'] ? 'splněno' : 'nesplněno'),
                        $row['note'] ?? '',
                    ]);

                    return CsvExport::download(
                        'prehled-'.$kpi->from->format('Y-m-d').'.csv',
                        ['ukazatel', 'skutecnost', 'cil', 'stav', 'poznamka'],
                        $rows,
                    );
                }),
        ];
    }
}
