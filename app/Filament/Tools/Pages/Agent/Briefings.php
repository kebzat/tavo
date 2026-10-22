<?php

namespace App\Filament\Tools\Pages\Agent;

use App\Filament\Tools\Pages\Ads\AdsClient;
use App\Filament\Tools\Resources\Clients\ClientResource;
use App\Models\Client;
use App\Models\ClientTask;
use App\Models\Marketing\ClientBriefing;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;

/**
 * Ranní přehled: co agent ráno zjistil u klientů (co se změnilo, jestli jsou
 * data spolehlivá, co hoří, navržený krok ke schválení) a co dnes čeká na
 * přihlášeného: úkoly s termínem, priority a návrhy úkolů ke schválení.
 */
class Briefings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static ?string $navigationLabel = 'Ranní přehled';

    protected static ?string $title = 'Ranní přehled';

    protected static ?string $slug = 'ranni-prehled';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.tools.pages.agent.briefings';

    /** Den přehledu, YYYY-MM-DD. Prázdné = dnes. */
    #[Url(as: 'den')]
    public ?string $day = null;

    public function getSubheading(): ?string
    {
        return 'Agent píše přehled ve všední dny v 6:50 z čísel, která ráno stáhl systém. Čísla nepočítá sám, jen je vykládá. Doporučení jsou návrhy ke schválení.';
    }

    public function date(): Carbon
    {
        return rescue(fn () => Carbon::parse($this->day ?? 'today')->startOfDay(), today(), report: false);
    }

    public function shiftDay(int $days): void
    {
        $date = $this->date()->addDays($days);
        $this->day = $date->isToday() ? null : $date->toDateString();
    }

    /** @return list<array<string, mixed>> */
    public function briefings(): array
    {
        return ClientBriefing::query()
            ->whereDate('date', $this->date())
            ->with('client')
            ->get()
            ->sortBy(fn (ClientBriefing $briefing): string => $briefing->client->name)
            ->map(function (ClientBriefing $briefing): array {
                $client = $briefing->client;

                return [
                    'briefing' => $briefing,
                    'client' => $client,
                    'alerts' => $client->adAlerts()->live()->count(),
                    'proposals' => $client->taskProposals()->count(),
                    'ads_url' => $client->activeAdAccountIds() !== [] ? AdsClient::getUrl(['client' => $client]) : null,
                    'client_url' => ClientResource::getUrl('edit', ['record' => $client]),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Úkoly přihlášeného, které hoří: po termínu, s termínem do dvou dnů,
     * nebo s vysokou prioritou.
     *
     * @return list<array<string, mixed>>
     */
    public function myTasks(): array
    {
        return ClientTask::query()
            ->with('client')
            ->where('is_proposal', false)
            ->open()
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', auth()->id()))
            ->where(fn ($query) => $query->where('due_on', '<=', today()->addDays(2))->orWhere('priority', 'high'))
            ->orderByRaw('due_on is null')
            ->orderBy('due_on')
            ->limit(30)
            ->get()
            ->map(fn (ClientTask $task): array => [
                'task' => $task,
                'overdue' => $task->due_on?->isPast() && ! $task->due_on->isToday(),
                'url' => ClientResource::getUrl('edit', ['record' => $task->client_id]),
            ])
            ->all();
    }

    /** @return list<array{client: Client, count: int, url: string}> */
    public function proposals(): array
    {
        return Client::query()
            ->whereHas('taskProposals')
            ->withCount('taskProposals')
            ->orderBy('name')
            ->get()
            ->map(fn (Client $client): array => [
                'client' => $client,
                'count' => $client->task_proposals_count,
                'url' => ClientResource::getUrl('edit', ['record' => $client]),
            ])
            ->all();
    }
}
