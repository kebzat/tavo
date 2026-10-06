<?php

namespace App\Filament\Tools\Pages;

use App\Support\RetainerOutlook;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Kolik bude chodit z paušálů v příštím roce a kde to klesá. Plán se zadává
 * u klienta (paušál s Od a Do, případně předběžně), tady se jen čte.
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

    public function getSubheading(): ?string
    {
        return 'Pravidelné příjmy na 12 měsíců dopředu podle paušálů u klientů.';
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $outlook = new RetainerOutlook;

        return [
            'months' => $outlook->monthHeaders(),
            'rows' => $outlook->rows(),
            'totals' => $outlook->totals(),
            'summary' => $outlook->summary(),
        ];
    }
}
