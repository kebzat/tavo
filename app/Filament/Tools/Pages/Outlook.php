<?php

namespace App\Filament\Tools\Pages;

use App\Enums\WorkArea;
use App\Models\User;
use App\Support\RetainerOutlook;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

/**
 * Kolik bude chodit z paušálů v příštím roce a kde to klesá, po oblastech
 * jako Fakturace: vývoj Tom, marketing Pavel. Plán se zadává u klienta
 * (paušál s Od a Do, případně předběžně), tady se jen čte.
 */
class Outlook extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTrendingUp;

    protected static ?string $navigationLabel = 'Výhled';

    protected static ?string $title = 'Výhled paušálů';

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 46;

    protected static ?string $slug = 'vyhled';

    protected string $view = 'filament.tools.pages.outlook';

    /** web, marketing nebo vse. Výchozí je oblast, kterou přihlášený fakturuje. */
    #[Url]
    public ?string $oblast = null;

    public function mount(): void
    {
        $this->oblast ??= Auth::user()?->billing_area?->value ?? 'vse';
    }

    public function getSubheading(): ?string
    {
        $area = WorkArea::tryFrom((string) $this->oblast);

        return 'Pravidelné příjmy na 12 měsíců dopředu podle paušálů u klientů'
            .($area ? ', jen '.mb_strtolower($area->getLabel()) : '').'.';
    }

    public function showArea(string $area): void
    {
        $this->oblast = WorkArea::tryFrom($area)?->value ?? 'vse';
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $outlook = new RetainerOutlook(area: WorkArea::tryFrom((string) $this->oblast));

        return [
            'filters' => $this->filters(),
            'months' => $outlook->monthHeaders(),
            'rows' => $outlook->rows(),
            'totals' => $outlook->totals(),
            'summary' => $outlook->summary(),
        ];
    }

    /**
     * Přepínač nahoře: Vše, Vývoj webu · Tom, Marketing · Pavel.
     *
     * @return list<array{key: string, label: string, active: bool}>
     */
    private function filters(): array
    {
        $filters = [['key' => 'vse', 'label' => 'Vše', 'active' => WorkArea::tryFrom((string) $this->oblast) === null]];

        foreach (WorkArea::cases() as $area) {
            $invoicer = User::query()->where('billing_area', $area->value)->orderBy('id')->pluck('name')->implode(', ');
            $filters[] = [
                'key' => $area->value,
                'label' => $area->getLabel().($invoicer !== '' ? ' · '.$invoicer : ''),
                'active' => $this->oblast === $area->value,
            ];
        }

        return $filters;
    }
}
