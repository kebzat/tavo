<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Vyúčtování hodin klientovi. Text připraví Fakturace, odešle ho člověk tlačítkem. */
class BillingStatementMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $body,
        public ?string $replyToEmail = null,
        public ?string $replyToName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
            replyTo: $this->replyToEmail ? [new Address($this->replyToEmail, $this->replyToName)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.billing-statement');
    }
}
