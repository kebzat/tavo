<?php

namespace App\Filament\Tools\Pages;

use App\Support\Crm\CsvExport;
use App\Support\Crm\OutreachLog;
use App\Support\Crm\WeeklyKpi;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Obchodní přehled. Jen informativní: co se za týden stalo, koho jsme
 * oslovili a jak zareagoval. Cíle ani kvóty tu nejsou (rozhodnutí Toma
 * z 28. 9. 2026).
 */
class Overview extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Přehled';

    protected static ?string $title = 'Přehled';

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
     * Čísla za zobrazený týden.
     *
     * @return Collection<int, array{label: string, value: int, note: ?string}>
     */
    public function rows(): Collection
    {
        $kpi = $this->kpi();
        $metrics = $kpi->metrics();

        return collect(WeeklyKpi::labels())->map(fn (string $label, string $key): array => [
            'label' => $label,
            'value' => $metrics[$key] ?? 0,
            'note' => $key === 'won' && $kpi->wonValue() > 0
                ? number_format($kpi->wonValue(), 0, ',', ' ').' Kč'
                : null,
        ])->values();
    }

    /** Oslovené firmy a jejich reakce, od posledního oslovení. */
    public function outreachLog(): Collection
    {
        return OutreachLog::rows();
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
                        $row['note'] ?? '',
                    ]);

                    return CsvExport::download(
                        'prehled-'.$kpi->from->format('Y-m-d').'.csv',
                        ['ukazatel', 'pocet', 'poznamka'],
                        $rows,
                    );
                }),
        ];
    }
}
