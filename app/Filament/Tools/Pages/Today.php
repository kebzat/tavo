<?php

namespace App\Filament\Tools\Pages;

use App\Filament\Tools\Actions\LogActivityAction;
use App\Filament\Tools\Actions\UseTemplateAction;
use App\Models\Crm\Company;
use App\Models\Crm\Demand;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * Co mám dneska udělat.
 *
 * Výchozí obrazovka po přihlášení. Neukazuje všechno, co v CRM je, ale jen to,
 * co dnes čeká: propásnuté follow-upy, dnešní termíny, zbytek týdne, nové
 * poptávky a firmy, na které se zapomnělo.
 */
class Today extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSun;

    protected static ?string $navigationLabel = 'Dnes';

    protected static ?string $title = 'Co mám dnes udělat';

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.tools.pages.today';

    /** Firmy s propásnutým termínem. */
    public function overdue(): Collection
    {
        return $this->companies(fn ($query) => $query->overdue());
    }

    /** Firmy, na které je termín na dnešek. */
    public function dueToday(): Collection
    {
        return $this->companies(fn ($query) => $query->dueToday());
    }

    /** Zbytek týdne — od zítřka do neděle. */
    public function dueThisWeek(): Collection
    {
        return $this->companies(fn ($query) => $query->dueThisWeek());
    }

    /**
     * Fronta k prvnímu oslovení, seřazená podle priority. Áčka nahoře,
     * ať se začíná tam, kde to nejspíš vyjde.
     */
    public function untouched(): Collection
    {
        return $this->companies(fn ($query) => $query->untouched()->orderBy('priority'), limit: 15);
    }

    /** Kolik firem čeká na první oslovení celkem, ne jen na obrazovce. */
    public function untouchedTotal(): int
    {
        return Company::query()->untouched()->count();
    }

    /** Rozjednané firmy, kde se týden nic nestalo. */
    public function stale(): Collection
    {
        return $this->companies(fn ($query) => $query->stale()->orderBy('last_activity_at'), limit: 15);
    }

    /** Poptávky, na které jsme ještě nereagovali. */
    public function newDemands(): Collection
    {
        return Demand::query()->untouched()->limit(15)->get();
    }

    /**
     * Firmy pro jeden blok i s poslední aktivitou — bez ní se z řádku nepozná,
     * kde jsme jednání nechali, a musí se otvírat karta.
     */
    private function companies(callable $filter, int $limit = 25): Collection
    {
        return $filter(Company::query())
            ->with(['owner', 'activities' => fn ($query) => $query->latest('happened_at')->limit(1)])
            ->workOrder()
            ->limit($limit)
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | Akce nad řádkem
    |--------------------------------------------------------------------------
    | Firma se do akcí předává v arguments — tahle stránka není tabulka
    | a žádný záznam by se do akce sám neinjektoval.
    */

    public function logActivityAction(): Action
    {
        return LogActivityAction::make();
    }

    public function useTemplateAction(): Action
    {
        return UseTemplateAction::make();
    }

    /** Odklad o pár dní. Posouvá existující follow-up, nezakládá další. */
    public function snooze(int $companyId, int $days): void
    {
        $company = Company::findOrFail($companyId);
        $company->snooze($days);

        Notification::make()
            ->success()
            ->title($company->name.' — odloženo o '.$days.' dní')
            ->body('Nový termín '.$company->refresh()->next_action_at?->format('j. n. Y'))
            ->send();
    }
}
