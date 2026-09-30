<?php

namespace App\Mail;

use App\Models\Ads\AdReport;
use App\Settings\ContactSettings;
use App\Support\Ads\PerformanceView;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Report reklam pro klienta. V e-mailu jen tři hlavní čísla a odkaz,
 * celý přehled s grafem a komentářem je na sdílené stránce.
 */
class AdReportMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var list<array{label: string, value: string, change: ?string}> */
    public array $tiles;

    public string $url;

    public string $period;

    public function __construct(public AdReport $report)
    {
        $view = new PerformanceView($report->snapshot);

        $this->tiles = array_map(
            fn (array $tile): array => ['label' => $tile['label'], 'value' => $tile['value'], 'change' => $tile['change']],
            array_slice($view->tiles(), 0, 3),
        );
        $this->url = (string) $report->publicUrl();
        $this->period = $view->period->label();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->report->type->title().' '.$this->period.' · '.$this->report->client->name,
            // Odpověď klienta má přijít nám, ne na odesílací adresu.
            replyTo: array_filter([app(ContactSettings::class)->email]),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.ad-report');
    }
}
