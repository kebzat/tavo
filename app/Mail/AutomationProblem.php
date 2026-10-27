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

/** Automatizace nejede (chyba běhu nebo Upgates nezměnil stav). Viz AutomationAlerts. */
class AutomationProblem extends Mailable
{
    use Queueable, SerializesModels;

    public string $url;

    public function __construct(
        public Automation $automation,
        public User $recipient,
        public string $problem,
        public string $advice,
    ) {
        $this->url = Automations::getUrl(['klient' => $recipient->isAdmin() ? $automation->client_id : null], panel: 'client');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Automatizace nejede: '.$this->automation->type->getLabel().' ('.$this->automation->client->name.')');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.automation-problem');
    }
}
