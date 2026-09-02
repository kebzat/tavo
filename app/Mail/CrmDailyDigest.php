<?php

namespace App\Mail;

use App\Filament\Tools\Pages\Today;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Ranní souhrn obchodu. Chodí ve všední dny v 7:00 a nahrazuje ranní
 * proklikání CRM — kdo si ho přečte, ví, co dnes čeká.
 */
class CrmDailyDigest extends Mailable
{
    use Queueable, SerializesModels;

    /** Odkaz do přehledu „Dnes". Skládá se tady, ať ho šablona jen vypíše. */
    public string $todayUrl;

    public function __construct(
        public User $recipient,
        public Collection $overdue,
        public Collection $dueToday,
        public Collection $demands,
        public Collection $stale,
    ) {
        $this->todayUrl = Today::getUrl(panel: 'tools');
    }

    public function envelope(): Envelope
    {
        $count = $this->overdue->count() + $this->dueToday->count();

        return new Envelope(
            // Počet v předmětu je jediná informace, kterou je vidět v seznamu
            // pošty — bez otevírání se pozná, jestli má e-mail počkat.
            subject: $count > 0
                ? "CRM: {$count} follow-upů na dnešek"
                : 'CRM: dnes žádné follow-upy',
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.crm-daily-digest');
    }
}
