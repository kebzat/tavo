<?php

namespace App\Mail;

use App\Filament\Client\Pages\Automations;
use App\Models\Automation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Ranní souhrn automatizace pro klienta: co se včera poslalo a co leží na výdejních místech. */
class AutomationSummary extends Mailable
{
    use Queueable, SerializesModels;

    public string $url;

    /**
     * @param  array<string, int>  $sent  počty potvrzených zpráv podle druhu (AutomationEvent::kindLabel)
     * @param  array<string, list<string>>  $orders  čísla objednávek k nim, ať klient ví, kterých se to týká
     */
    public function __construct(
        public Automation $automation,
        public User $recipient,
        public string $day,
        public array $sent,
        public int $failed,
        public int $parcels,
        public array $orders = [],
    ) {
        $this->url = Automations::getUrl(panel: 'client');
    }

    public function envelope(): Envelope
    {
        $total = array_sum($this->sent);

        return new Envelope(subject: $total > 0
            ? "Zásilky: včera {$total} ".($total === 1 ? 'zpráva' : ($total < 5 ? 'zprávy' : 'zpráv')).", na výdejních místech {$this->parcels}"
            : "Zásilky: včera nic, na výdejních místech {$this->parcels}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.automation-summary');
    }
}
