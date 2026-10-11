<?php

namespace App\Models\Concerns;

use App\Enums\Crm\ActivityType;
use App\Enums\Crm\CompanyStatus;
use App\Models\ClientView;
use App\Support\OurIps;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Počítá, kolikrát klient otevřel sdílený dokument (audit, nabídku spolupráce).
 *
 * Každé otevření se zapíše do client_views i s IP adresou. Sloupce
 * `view_count`, `first_viewed_at` a `last_viewed_at` se z nich dopočítají
 * bez našich adres (OurIps), takže ukazují jen klienta.
 *
 * Model potřebuje ty tři sloupce, k nim `views_before_log`,
 * `first_viewed_before_log_at` a `last_viewed_before_log_at`, vztah `client`
 * a atribut `title`.
 */
trait TracksClientViews
{
    /** Náhledy odkazů z e-mailu a chatu otevření nejsou. */
    private const BOT_PATTERN = '/bot|crawl|spider|preview|facebookexternalhit|whatsapp|telegram|skype|slack|discord|curl|wget|python|headless|lighthouse/i';

    /**
     * Popis aktivity v CRM, třeba „Otevřeli audit poprvé".
     */
    abstract protected function viewActivitySubject(bool $first): string;

    /** @return MorphMany<ClientView, $this> */
    public function clientViews(): MorphMany
    {
        return $this->morphMany(ClientView::class, 'viewable');
    }

    /**
     * Zapíše otevření odkazu. První otevření (a další po půl dni ticha)
     * se propíše do CRM jako aktivita s follow-upem na příští pracovní
     * den, protože právě tehdy má smysl zavolat.
     *
     * Roboti a náhledy odkazů se nepočítají, správce si dokument otevírá
     * přihlášený a ten volající nepředá. Otevření z naší adresy se zapíše,
     * ale do počtu ani do CRM se nepropíše.
     */
    public function recordView(?string $userAgent, ?string $ip = null): void
    {
        if (blank($userAgent) || preg_match(self::BOT_PATTERN, $userAgent)) {
            return;
        }

        $view = $this->clientViews()->create([
            'ip' => $ip,
            'network' => OurIps::network($ip),
            'user_agent' => mb_substr($userAgent, 0, 500),
            'viewed_at' => now(),
        ]);

        if (app(OurIps::class)->oursIds(collect([$view])) !== []) {
            return;
        }

        $first = $this->first_viewed_at === null;
        $worthLogging = $this->last_viewed_at === null || $this->last_viewed_at->lt(now()->subHours(12));

        $this->recountViews();

        $company = $this->client?->crmCompany;

        if (! $worthLogging || $company === null) {
            return;
        }

        $closed = in_array($company->status, [CompanyStatus::Won, CompanyStatus::Lost], true);

        $company->activities()->create([
            'type' => ActivityType::Note,
            'subject' => $this->viewActivitySubject($first),
            'body' => $this->title.' · otevřeno '.$this->view_count.'×',
            'happened_at' => now(),
            'follow_up_at' => $closed ? null : now()->nextWeekday()->setTime(9, 0),
        ]);
    }

    /**
     * Dopočítá počet a data otevření ze zápisů, bez našich adres. Otevření
     * z doby před zápisem po jednom (`views_before_log`) se jen přičtou,
     * IP adresu nemají a vyčistit nejdou.
     */
    public function recountViews(): void
    {
        $views = app(OurIps::class)->withoutOurs($this->clientViews()->orderBy('viewed_at')->orderBy('id')->get());

        $this->forceFill([
            'view_count' => $this->views_before_log + $views->count(),
            'first_viewed_at' => collect([$this->first_viewed_before_log_at, $views->first()?->viewed_at])->filter()->min(),
            'last_viewed_at' => collect([$this->last_viewed_before_log_at, $views->last()?->viewed_at])->filter()->max(),
        ])->saveQuietly();
    }

    /**
     * Poslední otevření pro výpis v administraci, i ta naše (označená).
     *
     * @return list<string>
     */
    public function viewLog(int $limit = 20): array
    {
        $views = $this->clientViews()->latest('viewed_at')->latest('id')->limit($limit)->get();
        $ours = app(OurIps::class)->oursIds($views);

        return $views
            ->map(fn (ClientView $view): string => $view->viewed_at->format('j. n. Y H:i')
                .' · '.$view->device()
                .' · '.($view->ip ?? 'bez IP')
                .(in_array($view->id, $ours, true) ? ' · my, nepočítá se' : ''))
            ->all();
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
