<?php

namespace App\Mail;

use App\Filament\Tools\Pages\Ads\AdsOverview;
use App\Filament\Tools\Pages\Agent\Briefings;
use App\Models\Ads\AdAlert;
use App\Models\Client;
use App\Models\Marketing\ClientBriefing;
use App\Models\User;
use App\Support\Ads\Format;
use App\Support\Ads\Metrics;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/** Ranní souhrn reklam pro nás dva. Chodí ve všední dny po kontrole. */
class AdsDailyDigest extends Mailable
{
    use Queueable, SerializesModels;

    public string $overviewUrl;

    public string $briefingsUrl;

    /** @var Collection<int, AdAlert> Nalezené dnes poprvé. */
    public Collection $newAlerts;

    /** @var Collection<int, AdAlert> Svítí už dřív, beze změny se jen připomínají. */
    public Collection $ongoingAlerts;

    /** Kolik klientů má dnes ranní přehled od agenta. */
    public int $briefings;

    /** @var list<array{client: string, spend: string, conversions: string, cost: string}> Včerejší čísla už naformátovaná. */
    public array $rows;

    /**
     * @param  Collection<int, AdAlert>  $alerts
     * @param  Collection<int, array{client: Client, metrics: Metrics}>  $clients
     */
    public function __construct(
        public User $recipient,
        public Collection $alerts,
        public Collection $clients,
    ) {
        $this->overviewUrl = AdsOverview::getUrl(panel: 'tools');
        $this->briefingsUrl = Briefings::getUrl(panel: 'tools');
        [$this->newAlerts, $this->ongoingAlerts] = $alerts->partition(fn (AdAlert $alert): bool => $alert->detected_on?->isToday() ?? false);
        $this->briefings = ClientBriefing::query()->whereDate('date', today())->count();
        $this->rows = $clients->map(function (array $row): array {
            $client = $row['client'];
            $goal = $client->adGoal();
            $currency = $client->adCurrency();

            return [
                'client' => $client->name,
                'spend' => Format::money($row['metrics']->spend(), $currency),
                'conversions' => Format::count($row['metrics']->conversions($goal)),
                'cost' => Format::unitPrice($row['metrics']->costPerConversion($goal), $currency),
            ];
        })->all();
    }

    public function envelope(): Envelope
    {
        // Předmět počítá jen nová upozornění, ať stejná situace nevypadá den co den jako novinka.
        $new = $this->newAlerts->count();
        $ongoing = $this->ongoingAlerts->count();

        return new Envelope(
            subject: match (true) {
                $new > 0 => "Reklamy: {$new} ".($new === 1 ? 'nové upozornění' : 'nová upozornění'),
                $ongoing > 0 => 'Reklamy: nic nového, '.$ongoing.' trvá',
                default => 'Reklamy: vše v klidu',
            },
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.ads-daily-digest');
    }
}
