<?php

namespace App\Filament\Tools\Pages;

use App\Enums\Crm\DealStage;
use App\Models\Crm\Deal;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * Kanban rozjednaných obchodů.
 *
 * Přesouvá se tažením karty; na dotykových zařízeních, kde HTML5 drag & drop
 * nefunguje, slouží k témuž roletka přímo na kartě. Bez balíčku navíc —
 * jediná operace je „změň fázi", na kterou by sortable knihovna byla zbytečná.
 */
class Pipeline extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewColumns;

    protected static ?string $navigationLabel = 'Pipeline';

    protected static ?string $title = 'Pipeline';

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 30;

    protected string $view = 'filament.tools.pages.pipeline';

    /**
     * Sloupce kanbanu i s obsahem a součty.
     *
     * @return Collection<int, array{stage: DealStage, deals: Collection<int, Deal>, total: int, weighted: int}>
     */
    public function columns(): Collection
    {
        $deals = Deal::query()
            ->whereIn('stage', array_column(DealStage::boardColumns(), 'value'))
            ->with(['company', 'owner'])
            ->orderByDesc('value_czk')
            ->get()
            ->groupBy(fn (Deal $deal): string => $deal->stage->value);

        return collect(DealStage::boardColumns())->map(function (DealStage $stage) use ($deals): array {
            /** @var Collection<int, Deal> $inStage */
            $inStage = $deals->get($stage->value, collect());

            return [
                'stage' => $stage,
                'deals' => $inStage,
                'total' => (int) $inStage->sum('value_czk'),
                // Zaokrouhluje se až součet, ne jednotlivé obchody — jinak by
                // se chyby zaokrouhlení nasčítaly do řádů tisíců.
                'weighted' => (int) round($inStage->sum(fn (Deal $deal): float => $deal->weightedValue())),
            ];
        });
    }

    /** Přesun karty do jiné fáze. Zbytek (datum, pravděpodobnost) řeší observer. */
    public function moveDeal(int $dealId, string $stage): void
    {
        $target = DealStage::tryFrom($stage);
        $deal = Deal::find($dealId);

        if ($target === null || $deal === null || $deal->stage === $target) {
            return;
        }

        $deal->update(['stage' => $target]);

        Notification::make()
            ->success()
            ->title($deal->title)
            ->body('Přesunuto do fáze „'.$target->getLabel().'".')
            ->send();
    }

    /** Roletka na kartě — na mobilu jediná cesta, jak fázi změnit. */
    public function stageOptions(): array
    {
        return collect(DealStage::cases())
            ->mapWithKeys(fn (DealStage $stage): array => [$stage->value => $stage->getLabel()])
            ->all();
    }
}
