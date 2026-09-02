<?php

namespace App\Support\Crm;

use App\Enums\Crm\ActivityOutcome;
use App\Enums\Crm\ActivityType;
use App\Enums\Crm\CompanyStatus;
use App\Models\Crm\Activity;
use App\Models\Crm\Company;
use App\Models\Crm\Deal;
use App\Models\Crm\Demand;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Týdenní obchodní čísla.
 *
 * Týden je pondělí–neděle podle času, kdy se věc stala (`happened_at`), ne
 * podle toho, kdy jsme ji zapsali. Aktivity se logují i zpětně a jinak by
 * páteční hovor zapsaný v pondělí spadl do špatného týdne.
 */
class WeeklyKpi
{
    public readonly Carbon $from;

    public readonly Carbon $to;

    public function __construct(?Carbon $weekStart = null)
    {
        $this->from = ($weekStart ?? Carbon::now())->copy()->startOfWeek();
        $this->to = $this->from->copy()->endOfWeek();
    }

    /** Popisky řádků v přehledu. Klíče sedí na výsledek metrics(). */
    public static function labels(): array
    {
        return [
            'outreach' => 'Nová oslovení',
            'follow_ups' => 'Follow-upy',
            'replies' => 'Odpovědi',
            'calls' => 'Hovory a schůzky',
            'proposals' => 'Odeslané nabídky',
            'won' => 'Vyhráno',
            'demand_replies' => 'Reakce na poptávky',
        ];
    }

    /**
     * Spočítaná čísla za týden.
     *
     * @return array<string, int>
     */
    public function metrics(): array
    {
        [$outreach, $followUps] = $this->outreachSplit();

        return [
            'outreach' => $outreach,
            'follow_ups' => $followUps,
            'replies' => $this->replies(),
            'calls' => $this->calls(),
            'proposals' => $this->proposals(),
            'won' => $this->wonCount(),
            'demand_replies' => $this->demandReplies(),
        ];
    }

    /**
     * Rozdělení oslovení na první kontakt a follow-up.
     *
     * První oslovení firmy je to, před kterým u ní žádné jiné oslovení není.
     * Počítá se z celé historie, ne jen z tohohle týdne — firma oslovená
     * v srpnu je v září follow-up, ne nové oslovení.
     *
     * @return array{0: int, 1: int}
     */
    private function outreachSplit(): array
    {
        $types = array_column(ActivityType::outreach(), 'value');

        $activities = Activity::query()
            ->whereIn('type', $types)
            ->happenedBetween($this->from, $this->to)
            ->orderBy('happened_at')
            ->get(['id', 'company_id', 'happened_at']);

        if ($activities->isEmpty()) {
            return [0, 0];
        }

        // Nejstarší oslovení každé z dotčených firem. Jedním dotazem, ať se
        // pro každou aktivitu nechodí do databáze zvlášť.
        $firstEver = Activity::query()
            ->whereIn('type', $types)
            ->whereIn('company_id', $activities->pluck('company_id')->unique())
            ->groupBy('company_id')
            ->selectRaw('company_id, min(happened_at) as first_at')
            ->pluck('first_at', 'company_id');

        $outreach = 0;
        $followUps = 0;

        foreach ($activities as $activity) {
            $first = $firstEver[$activity->company_id] ?? null;

            $isFirst = $first !== null
                && Carbon::parse($first)->equalTo($activity->happened_at);

            $isFirst ? $outreach++ : $followUps++;
        }

        return [$outreach, $followUps];
    }

    /**
     * Kolik firem nám tenhle týden odpovědělo.
     *
     * Počítají se firmy, ne aktivity — tři e-maily od jednoho zájemce je pořád
     * jedna odpověď. Kromě zaznamenaného výsledku aktivity se berou i firmy
     * ručně přepnuté do stavu „Odpověděli", protože odpověď občas zapíšeme
     * jen změnou stavu.
     */
    private function replies(): int
    {
        $fromActivities = Activity::query()
            ->whereIn('outcome', array_column(ActivityOutcome::answered(), 'value'))
            ->happenedBetween($this->from, $this->to)
            ->distinct()
            ->pluck('company_id');

        $fromStatus = Company::query()
            ->where('status', CompanyStatus::Replied)
            ->whereBetween('updated_at', [$this->from, $this->to])
            ->pluck('id');

        return $fromActivities->merge($fromStatus)->unique()->count();
    }

    /** Hovory a schůzky, u kterých jsme se s někým doopravdy bavili. */
    private function calls(): int
    {
        return Activity::query()
            ->whereIn('type', [ActivityType::Call->value, ActivityType::Meeting->value])
            ->where(fn ($query) => $query
                ->whereNull('outcome')
                ->orWhere('outcome', '!=', ActivityOutcome::NoAnswer->value))
            ->happenedBetween($this->from, $this->to)
            ->count();
    }

    private function proposals(): int
    {
        return Deal::query()
            ->whereBetween('proposal_sent_at', [$this->from, $this->to])
            ->count();
    }

    private function wonCount(): int
    {
        return Deal::query()->whereBetween('won_at', [$this->from, $this->to])->count();
    }

    /** Vyhraná částka za týden. V tabulce doplňuje počet vyhraných obchodů. */
    public function wonValue(): int
    {
        return (int) Deal::query()
            ->whereBetween('won_at', [$this->from, $this->to])
            ->sum('value_czk');
    }

    private function demandReplies(): int
    {
        return Demand::query()
            ->whereBetween('replied_at', [$this->from, $this->to])
            ->count();
    }

    /**
     * Posledních osm týdnů pro sloupcový graf. Vrací se od nejstaršího,
     * ať se dá vykreslit zleva doprava.
     *
     * @return Collection<int, array{label: string, start: Carbon, outreach: int, replies: int, proposals: int}>
     */
    public static function lastWeeks(int $weeks = 8): Collection
    {
        return collect(range($weeks - 1, 0))
            ->map(function (int $offset): array {
                $kpi = new self(Carbon::now()->subWeeks($offset));
                $metrics = $kpi->metrics();

                return [
                    'label' => $kpi->from->format('j. n.'),
                    'start' => $kpi->from,
                    'outreach' => $metrics['outreach'],
                    'replies' => $metrics['replies'],
                    'proposals' => $metrics['proposals'],
                ];
            });
    }
}
