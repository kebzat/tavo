<?php

namespace App\Mail;

use App\Filament\Tools\Pages\Ads\AdsOverview;
use App\Models\Ads\AdAlert;
use App\Models\Client;
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
        $count = $this->alerts->count();

        return new Envelope(
            subject: $count > 0 ? "Reklamy: {$count} upozornění" : 'Reklamy: vše v klidu',
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.ads-daily-digest');
    }
}
