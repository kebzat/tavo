<?php

namespace App\Support\Ads;

use App\Enums\Ads\ReportStatus;
use App\Filament\Tools\Resources\AdAlerts\AdAlertResource;
use App\Filament\Tools\Resources\AdEvents\AdEventResource;
use App\Filament\Tools\Resources\AdReports\AdReportResource;
use App\Models\Ads\AdAlert;
use App\Models\Ads\AdEvent;
use App\Models\Ads\AdReport;
use App\Models\Client;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Ranní souhrn nahoře v přehledu klientů, v ranním e-mailu a pro agenta:
 * „2 problémy s daty, 3 výkonové odchylky, 1 končící licence, 2 reporty
 * ke kontrole“. Ať Pavel ráno hned ví, čemu věřit a kde zasáhnout.
 */
final class MorningSummary
{
    public function __construct(private readonly CarbonImmutable $today) {}

    public static function for(?CarbonInterface $today = null): self
    {
        return new self(CarbonImmutable::parse($today ?? today())->startOfDay());
    }

    /**
     * Položky souhrnu, jen nenulové. Pořadí: data, výkon, termíny, reporty, kampaně.
     *
     * @return list<array{key: string, count: int, label: string, url: string, color: string}>
     */
    public function items(): array
    {
        $alerts = AdAlert::query()->visible($this->today)->get(['id', 'category', 'severity']);
        $data = $alerts->where('category', AdAlert::DATA)->count() + $this->staleClients();
        $performance = $alerts->where('category', AdAlert::PERFORMANCE)->count();
        $events = AdEvent::query()->due($this->today)->get(['id', 'type', 'due_on']);
        $licenses = $events->filter(fn (AdEvent $event): bool => $event->type->value === 'license_end')->count();
        $otherEvents = $events->count() - $licenses;
        $overdue = $events->filter(fn (AdEvent $event): bool => $event->due_on->lt($this->today))->count();
        $reports = AdReport::query()->where('status', ReportStatus::Draft)->count();
        $waiting = Client::query()->withAds()->whereHas('adCampaignGroups')->with('adAccounts')->get()
            ->sum(fn (Client $client): int => CampaignGroups::waiting($client)->count());

        return array_values(array_filter([
            $data > 0 ? ['key' => 'data', 'count' => $data, 'label' => self::plural($data, 'problém s daty', 'problémy s daty', 'problémů s daty'), 'url' => AdAlertResource::getUrl(panel: 'tools').'?kategorie=data', 'color' => 'danger'] : null,
            $performance > 0 ? ['key' => 'performance', 'count' => $performance, 'label' => self::plural($performance, 'výkonová odchylka', 'výkonové odchylky', 'výkonových odchylek'), 'url' => AdAlertResource::getUrl(panel: 'tools').'?kategorie=performance', 'color' => 'warning'] : null,
            $licenses > 0 ? ['key' => 'licenses', 'count' => $licenses, 'label' => self::plural($licenses, 'končící licence', 'končící licence', 'končících licencí'), 'url' => AdEventResource::getUrl(panel: 'tools'), 'color' => $overdue > 0 ? 'danger' : 'warning'] : null,
            $otherEvents > 0 ? ['key' => 'events', 'count' => $otherEvents, 'label' => self::plural($otherEvents, 'blížící se termín', 'blížící se termíny', 'blížících se termínů'), 'url' => AdEventResource::getUrl(panel: 'tools'), 'color' => $overdue > 0 ? 'danger' : 'info'] : null,
            $reports > 0 ? ['key' => 'reports', 'count' => $reports, 'label' => self::plural($reports, 'report ke kontrole', 'reporty ke kontrole', 'reportů ke kontrole'), 'url' => AdReportResource::getUrl(panel: 'tools'), 'color' => 'info'] : null,
            $waiting > 0 ? ['key' => 'campaigns', 'count' => $waiting, 'label' => self::plural($waiting, 'nová kampaň k zařazení', 'nové kampaně k zařazení', 'nových kampaní k zařazení'), 'url' => '', 'color' => 'gray'] : null,
        ]));
    }

    /** Jedna věta: „2 problémy s daty, 3 výkonové odchylky…“. Prázdná, když je klid. */
    public function sentence(): string
    {
        return collect($this->items())->map(fn (array $item): string => $item['count'].' '.$item['label'])->implode(', ');
    }

    /**
     * Klienti, u kterých data nejsou kompletní, a přitom jim ještě nesvítí
     * upozornění na výpadek (třeba účet propojený dnes).
     */
    private function staleClients(): int
    {
        return Client::query()->withAds()->with('adAccounts')->get()
            ->filter(fn (Client $client): bool => ! DataStatus::for($client->adAccounts, $this->today)->isFresh()
                && ! $client->adAlerts()->visible($this->today)->where('rule', 'sync_failed')->exists())
            ->count();
    }

    private static function plural(int $count, string $one, string $few, string $many): string
    {
        return match (true) {
            $count === 1 => $one,
            $count >= 2 && $count <= 4 => $few,
            default => $many,
        };
    }
}
