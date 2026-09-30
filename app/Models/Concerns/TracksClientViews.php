<?php

namespace App\Models\Concerns;

use App\Enums\Crm\ActivityType;
use App\Enums\Crm\CompanyStatus;

/**
 * Počítá, kolikrát klient otevřel sdílený dokument (audit, nabídku spolupráce).
 *
 * Model potřebuje sloupce `view_count`, `first_viewed_at`, `last_viewed_at`,
 * vztah `client` a atribut `title`.
 */
trait TracksClientViews
{
    /** Náhledy odkazů z e-mailu a chatu otevření nejsou. */
    private const BOT_PATTERN = '/bot|crawl|spider|preview|facebookexternalhit|whatsapp|telegram|skype|slack|discord|curl|wget|python|headless|lighthouse/i';

    /**
     * Popis aktivity v CRM, třeba „Otevřeli audit poprvé".
     */
    abstract protected function viewActivitySubject(bool $first): string;

    /**
     * Zapíše otevření odkazu. První otevření (a další po půl dni ticha)
     * se propíše do CRM jako aktivita s follow-upem na příští pracovní
     * den, protože právě tehdy má smysl zavolat.
     *
     * Roboti a náhledy odkazů se nepočítají, správce si dokument otevírá
     * přihlášený a ten volající nepředá.
     */
    public function recordView(?string $userAgent): void
    {
        if (blank($userAgent) || preg_match(self::BOT_PATTERN, $userAgent)) {
            return;
        }

        $worthLogging = $this->last_viewed_at === null || $this->last_viewed_at->lt(now()->subHours(12));

        $this->forceFill([
            'view_count' => $this->view_count + 1,
            'first_viewed_at' => $this->first_viewed_at ?? now(),
            'last_viewed_at' => now(),
        ])->saveQuietly();

        $company = $this->client?->crmCompany;

        if (! $worthLogging || $company === null) {
            return;
        }

        $closed = in_array($company->status, [CompanyStatus::Won, CompanyStatus::Lost], true);

        $company->activities()->create([
            'type' => ActivityType::Note,
            'subject' => $this->viewActivitySubject($this->view_count === 1),
            'body' => $this->title.' · otevřeno '.$this->view_count.'×',
            'happened_at' => now(),
            'follow_up_at' => $closed ? null : now()->nextWeekday()->setTime(9, 0),
        ]);
    }

    /** Řádek „Otevřeno" v administraci. */
    public function viewSummary(string $never): string
    {
        if ($this->view_count === 0) {
            return $never;
        }

        return $this->view_count.'× · poprvé '.$this->first_viewed_at->format('j. n. Y H:i')
            .', naposledy '.$this->last_viewed_at->format('j. n. Y H:i');
    }
}
