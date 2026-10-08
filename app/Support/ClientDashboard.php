<?php

namespace App\Support;

use App\Enums\TaskStatus;
use App\Enums\WorkArea;
use App\Models\Ads\AdReport;
use App\Models\Audit;
use App\Models\Checklist;
use App\Models\Client;
use App\Models\ClientMonth;
use App\Models\ClientRetainer;
use App\Models\ClientTask;
use App\Models\TimeEntry;
use App\Support\Ads\Format;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Přehled spolupráce pro klienta za jeden měsíc: za co platí, kolik hodin
 * jsme odpracovali, co je hotové, co čeká na něj a co je v plánu.
 *
 * Klient vidí jen fakturovatelný čas, sečtený po úkolech. Jednotlivé zápisy
 * ani jejich popisy ven nejdou, ty píšeme pro sebe. Čísla jsou stejná jako
 * ve Fakturaci, viz App\Support\Ads\Billing.
 */
final class ClientDashboard
{
    /** Kolik měsíců ukazuje graf hodin. */
    private const HISTORY_MONTHS = 12;

    /** Kolik posledních měsíců má vlastní záložku, starší jsou v seznamu. */
    private const MONTH_TABS = 6;

    /** @var Collection<int, TimeEntry>|null */
    private ?Collection $entries = null;

    private function __construct(
        public readonly Client $client,
        public readonly CarbonImmutable $month,
    ) {}

    /** Měsíc ve tvaru Y-m z adresy. Mimo rozsah spolupráce spadne na nejbližší platný. */
    public static function for(Client $client, ?string $month = null): self
    {
        $current = CarbonImmutable::now()->startOfMonth();
        $first = self::firstMonthOf($client);
        $parsed = $month && preg_match('/^\d{4}-\d{2}$/', $month) ? CarbonImmutable::parse($month.'-01') : $current;

        return new self($client, $parsed->max($first)->min($current));
    }

    private static function firstMonthOf(Client $client): CarbonImmutable
    {
        $firstEntry = $client->timeEntries()->min('worked_on');
        $candidates = array_filter([$client->started_on, $firstEntry]);

        return $candidates
            ? CarbonImmutable::parse(min(array_map(fn ($date): string => CarbonImmutable::parse($date)->toDateString(), $candidates)))->startOfMonth()
            : CarbonImmutable::now()->startOfMonth();
    }

    public function label(): string
    {
        return Str::ucfirst($this->month->translatedFormat('F Y'));
    }

    public function isCurrentMonth(): bool
    {
        return $this->month->isSameMonth(CarbonImmutable::now());
    }

    /**
     * Výběr měsíce nad dlaždicemi: posledních šest měsíců jako záložky
     * (nejnovější vpravo), starší v rozbalovacím seznamu (nejnovější nahoře).
     * U záložek rok jen tehdy, když není letošní.
     *
     * @return array{recent: list<array{key: string, label: string, active: bool}>, older: list<array{key: string, label: string, active: bool}>, older_active: bool}
     */
    public function months(): array
    {
        $now = CarbonImmutable::now();
        $all = $this->range();
        $recent = array_slice($all, -self::MONTH_TABS);
        $older = array_reverse(array_slice($all, 0, max(0, count($all) - self::MONTH_TABS)));

        $item = fn (CarbonImmutable $month, string $format): array => [
            'key' => $month->format('Y-m'),
            'label' => Str::ucfirst($month->translatedFormat($format)),
            'active' => $month->isSameMonth($this->month),
        ];

        return [
            'recent' => array_map(fn (CarbonImmutable $month): array => $item($month, $month->year === $now->year ? 'F' : 'F Y'), $recent),
            'older' => array_map(fn (CarbonImmutable $month): array => $item($month, 'F Y'), $older),
            'older_active' => collect($older)->contains(fn (CarbonImmutable $month): bool => $month->isSameMonth($this->month)),
        ];
    }

    /**
     * Měsíce od začátku spolupráce do dneška, případně jen posledních pár.
     *
     * @return list<CarbonImmutable>
     */
    private function range(?int $last = null): array
    {
        $now = CarbonImmutable::now()->startOfMonth();
        $from = self::firstMonthOf($this->client);
        $months = [];

        if ($last !== null) {
            $from = $from->max($now->subMonthsNoOverflow($last - 1));
        }

        for ($month = $from; $month->lte($now); $month = $month->addMonthNoOverflow()) {
            $months[] = $month;
        }

        return $months;
    }

    /** Fakturovatelný čas v měsíci. */
    private function entries(): Collection
    {
        return $this->entries ??= $this->client->timeEntries()
            ->inMonth($this->month->year, $this->month->month)
            ->where('billable', true)
            ->with(['task', 'user'])
            ->get();
    }

    /** Oblast zápisu: z úkolu, jinak ze zápisu, jinak jediný paušál klienta. */
    private function areaOf(TimeEntry $entry): ?WorkArea
    {
        return $entry->task?->area ?? $entry->area ?? ($this->retainersInMonth()->count() === 1 ? $this->retainersInMonth()->first()->area : null);
    }

    /** @return Collection<int, ClientRetainer> */
    private function retainersInMonth(): Collection
    {
        return $this->client->retainers->filter(fn (ClientRetainer $retainer): bool => $retainer->billedIn($this->month))->values();
    }

    public function totalHours(): string
    {
        return self::hours($this->entries()->sum('minutes') / 60);
    }

    public function totalFee(): ?string
    {
        $fee = $this->retainersInMonth()->sum('monthly_fee');

        return $fee > 0 ? Format::money($fee) : null;
    }

    /**
     * Paušál po oblastech s hodinami za měsíc.
     *
     * @return list<array{label: string, fee: string, hours: string, included: ?string, percent: ?int, bar: string}>
     */
    public function retainers(): array
    {
        return $this->retainersInMonth()->map(function (ClientRetainer $retainer): array {
            $hours = $this->entries()->filter(fn (TimeEntry $entry): bool => $this->areaOf($entry) === $retainer->area)->sum('minutes') / 60;

            return [
                'label' => $retainer->label,
                'fee' => Format::money($retainer->monthly_fee),
                'hours' => self::hours($hours),
                'included' => $retainer->included_hours ? self::hours($retainer->included_hours) : null,
                'percent' => $retainer->included_hours ? (int) min(100, round($hours / $retainer->included_hours * 100)) : null,
                'bar' => $retainer->area->barClasses(),
            ];
        })->all();
    }

    /**
     * Dlaždice paušálů mají smysl u víc oblastí nebo u hodin v paušálu.
     * Jediný paušál bez stropu řekne hlavička sama.
     */
    public function showsRetainers(): bool
    {
        return $this->retainersInMonth()->count() > 1
            || $this->retainersInMonth()->contains(fn (ClientRetainer $retainer): bool => $retainer->included_hours !== null);
    }

    /**
     * Práce v měsíci: úkoly s odpracovaným časem nebo dokončené v měsíci.
     * Čas zapsaný bez úkolu se do seznamu nepropisuje, klient ho vidí jen
     * v čerpání paušálu nahoře.
     *
     * @return list<array<string, mixed>>
     */
    public function work(): array
    {
        $byTask = $this->entries()->whereNotNull('task_id')->groupBy('task_id');
        $doneIds = $this->client->tasks()
            ->where('status', TaskStatus::Done->value)
            ->whereBetween('done_on', [$this->month->toDateString(), $this->month->endOfMonth()->toDateString()])
            ->pluck('id');

        $tasks = $this->client->tasks()
            ->with('user')
            ->whereIn('id', $byTask->keys()->merge($doneIds)->unique())
            ->get()
            ->sortBy([
                fn (ClientTask $a, ClientTask $b): int => $this->statusOrder($a->status) <=> $this->statusOrder($b->status),
                fn (ClientTask $a, ClientTask $b): int => ($byTask->get($b->id)?->sum('minutes') ?? 0) <=> ($byTask->get($a->id)?->sum('minutes') ?? 0),
            ]);

        return $tasks->map(fn (ClientTask $task): array => $this->taskRow($task, $byTask->get($task->id, collect())))->values()->all();
    }

    /**
     * Úkoly, které stojí na klientovi. Jen v aktuálním měsíci, u minulých
     * by ukazovaly dnešní stav, ne tehdejší.
     *
     * @return list<array<string, mixed>>
     */
    public function waiting(): array
    {
        if (! $this->isCurrentMonth()) {
            return [];
        }

        return $this->client->tasks()->with('user')
            ->where('status', TaskStatus::Waiting->value)
            ->orderBy('planned_for')->orderBy('id')
            ->get()
            ->map(fn (ClientTask $task): array => $this->taskRow($task, collect()))
            ->all();
    }

    /**
     * Otevřené úkoly po měsících, kdy na ně dojde. Co mělo být dřív a není
     * hotové, patří do aktuálního měsíce. Jen v aktuálním měsíci.
     *
     * @return list<array{label: string, tasks: list<array<string, mixed>>}>
     */
    public function plan(): array
    {
        if (! $this->isCurrentMonth()) {
            return [];
        }

        $current = $this->month;

        // Na čem se tento měsíc už pracovalo, ukazuje „Co jsme udělali“.
        $worked = $this->entries()->pluck('task_id')->filter()->unique();

        return $this->client->tasks()->with('user')
            ->whereIn('status', [TaskStatus::Planned->value, TaskStatus::InProgress->value])
            ->whereNotIn('id', $worked)
            ->orderBy('planned_for')->orderBy('id')
            ->get()
            ->groupBy(fn (ClientTask $task): string => match (true) {
                $task->planned_for !== null => CarbonImmutable::parse($task->planned_for)->max($current)->format('Y-m'),
                $task->status === TaskStatus::InProgress => $current->format('Y-m'),
                default => 'later',
            })
            ->sortKeys()
            ->map(fn (Collection $tasks, string $key): array => [
                'label' => $key === 'later'
                    ? text('client_dashboard.plan_later', 'Později')
                    : Str::ucfirst(CarbonImmutable::parse($key.'-01')->translatedFormat('F Y')),
                // Nad měsícem „Tento měsíc“ nebo „Příští měsíc“, ať se v plánu hned zorientuje.
                'eyebrow' => match ($key) {
                    $current->format('Y-m') => text('client_dashboard.plan_this_month', 'Tento měsíc'),
                    $current->addMonthNoOverflow()->format('Y-m') => text('client_dashboard.plan_next_month', 'Příští měsíc'),
                    default => null,
                },
                'count' => trans_choice('{1} :count úkol|[2,4] :count úkoly|[5,*] :count úkolů', $tasks->count(), ['count' => $tasks->count()]),
                'tasks' => $tasks
                    ->sortBy(fn (ClientTask $task): int => $this->statusOrder($task->status))
                    ->map(fn (ClientTask $task): array => $this->taskRow($task, collect()))
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Hodiny po měsících od začátku spolupráce, rozdělené po oblastech.
     *
     * @return list<array{label: string, title: string, total: string, height: int, current: bool, segments: list<array{bar: string, height: int, label: string}>}>
     */
    public function history(): array
    {
        $months = $this->range(self::HISTORY_MONTHS);
        $from = $months[0];

        $entries = $this->client->timeEntries()
            ->where('billable', true)
            ->where('worked_on', '>=', $from->toDateString())
            ->with('task')
            ->get()
            ->groupBy(fn (TimeEntry $entry): string => $entry->worked_on->format('Y-m'));

        $max = max(1, ...array_map(fn (CarbonImmutable $month): int => (int) ($entries->get($month->format('Y-m'))?->sum('minutes') ?? 0), $months));

        return array_map(function (CarbonImmutable $month) use ($entries, $max): array {
            $inMonth = $entries->get($month->format('Y-m'), collect());
            $total = (int) $inMonth->sum('minutes');

            $segments = $inMonth->groupBy(fn (TimeEntry $entry): string => $this->areaOf($entry)?->value ?? '')
                ->sortKeys()
                ->map(fn (Collection $entries, string $area): array => [
                    'bar' => WorkArea::tryFrom($area)?->barClasses() ?? 'bg-muted',
                    'height' => (int) round($entries->sum('minutes') / $max * 100),
                    'label' => (WorkArea::tryFrom($area)?->getLabel() ?? text('client_dashboard.other_area', 'Ostatní')).' '.self::hours($entries->sum('minutes') / 60),
                ])
                ->values()
                ->all();

            return [
                'label' => Str::ucfirst($month->translatedFormat('M')),
                'title' => Str::ucfirst($month->translatedFormat('F Y')),
                'total' => self::hours($total / 60),
                'height' => (int) round($total / $max * 100),
                'current' => $month->isSameMonth($this->month),
                'segments' => $segments,
            ];
        }, $months);
    }

    /** @return list<array{label: string, bar: string}> */
    public function legend(): array
    {
        return $this->client->retainers
            ->map(fn (ClientRetainer $retainer): WorkArea => $retainer->area)
            ->unique()
            ->map(fn (WorkArea $area): array => ['label' => $area->getLabel(), 'bar' => $area->barClasses()])
            ->values()
            ->all();
    }

    public function goal(): ?string
    {
        return $this->note()?->goal ?: null;
    }

    public function summary(): ?HtmlString
    {
        $summary = $this->note()?->summary;

        return filled($summary)
            ? new HtmlString(Str::markdown($summary, ['html_input' => 'strip', 'allow_unsafe_links' => false]))
            : null;
    }

    private function note(): ?ClientMonth
    {
        return $this->client->months->first(fn (ClientMonth $note): bool => $note->month->isSameMonth($this->month));
    }

    /**
     * Sdílené dokumenty klienta: audity, checklisty, reporty reklam.
     *
     * @return list<array{label: string, meta: string, url: string}>
     */
    public function documents(): array
    {
        $audits = $this->client->audits()->public()->latest('audited_at')->get()
            ->map(fn (Audit $audit): array => [
                'label' => $audit->title,
                'meta' => text('client_dashboard.doc_audit', 'Audit').($audit->audited_at ? ' · '.$audit->audited_at->format('j. n. Y') : ''),
                'url' => $audit->publicUrl(),
            ]);

        $checklists = $this->client->checklists()->forClients()->where('is_public', true)->ordered()->get()
            ->map(fn (Checklist $checklist): array => [
                'label' => $checklist->name,
                'meta' => text('client_dashboard.doc_checklist', 'Checklist').' · '.text('client_dashboard.done', 'hotovo').' '.$checklist->progress()['percent'].' %',
                'url' => $checklist->publicUrl(),
            ]);

        $reports = $this->client->adReports()->public()->latest('period_start')->limit(6)->get()
            ->map(fn (AdReport $report): array => [
                'label' => $report->title,
                'meta' => text('client_dashboard.doc_report', 'Report reklam').' · '.$report->period_start->format('j. n.').' – '.$report->period_end->format('j. n. Y'),
                'url' => $report->publicUrl(),
            ]);

        return $audits->concat($checklists)->concat($reports)->filter(fn (array $doc): bool => filled($doc['url']))->values()->all();
    }

    /** @param  Collection<int, TimeEntry>  $entries */
    private function taskRow(ClientTask $task, Collection $entries): array
    {
        return [
            'title' => $task->title,
            'description' => $task->descriptionHtml(),
            'area' => $task->area->getLabel(),
            'area_classes' => $task->area->badgeClasses(),
            'status' => $task->status->clientLabel(),
            'status_classes' => $task->status->badgeClasses(),
            'people' => $this->people($entries) ?: $task->user?->name,
            'hours' => $entries->isNotEmpty() ? self::hours($entries->sum('minutes') / 60) : null,
        ];
    }

    /** „Pavel, Tom“ podle toho, kdo čas zapsal. */
    private function people(Collection $entries): ?string
    {
        $names = $entries->map(fn (TimeEntry $entry): ?string => $entry->user?->name)->filter()->unique()->sort()->values();

        return $names->isNotEmpty() ? $names->implode(', ') : null;
    }

    private function statusOrder(TaskStatus $status): int
    {
        return match ($status) {
            TaskStatus::Done => 0,
            TaskStatus::InProgress => 1,
            TaskStatus::Waiting => 2,
            TaskStatus::Planned => 3,
        };
    }

    /** „6,5 h“, celé hodiny bez desetinné čárky. */
    public static function hours(float $hours): string
    {
        $hours = round($hours, 1);

        return Format::number($hours, floor($hours) == $hours ? 0 : 1)."\u{00A0}h";
    }
}
