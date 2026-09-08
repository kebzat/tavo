<?php

namespace App\Mail;

use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LeadReceived extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Odkaz na poptávku v administraci. Skládá ho Filament, ať se adresa
     * nerozejde s routami: samotné `/admin/leads/6` vrací 404, detail je
     * až na `/edit`.
     */
    public string $adminUrl;

    public function __construct(public Lead $lead)
    {
        $this->adminUrl = LeadResource::getUrl('edit', ['record' => $lead]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nová poptávka z webu: '.$this->lead->name,
            replyTo: [$this->lead->email],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.lead-received');
    }
}
