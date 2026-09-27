<?php

namespace App\Support\Crm\Scout;

use App\Enums\Crm\ActivityType;
use App\Enums\Crm\CompanySource;
use App\Enums\Crm\CompanyStatus;
use App\Enums\Crm\FitVerdict;
use App\Models\Crm\Company;
use App\Support\Crm\Ai\ProspectAi;

/**
 * Proklepnutí firmy: změří web, sestaví nálezy, spočítá skóre a výsledek
 * uloží na kartu firmy.
 *
 * Zároveň doplní, co na kartě chybí a z webu se dá vyčíst: platformu,
 * hlavní bolest pro šablony zpráv a kontakt. Co už někdo vyplnil ručně,
 * nechává být.
 */
class ProspectScout
{
    public function __construct(
        private readonly WebScout $web,
        private readonly ProspectAi $ai,
    ) {}

    public function scout(Company $company): Company
    {
        $url = $company->websiteUrl();

        $measurements = $url !== null
            ? $this->web->measure($url)
            : ['reachable' => false, 'error' => 'Firma nemá vyplněný web.', 'measured_at' => now()->toIso8601String()];

        $findings = Findings::from($measurements);
        $fit = FitScorer::score($measurements, $findings, $company->segment);

        $scout = [
            'measurements' => $measurements,
            'findings' => $findings,
            'passed' => Findings::passed($measurements),
            'reasons' => $fit['reasons'],
            'base_score' => $fit['score'],
            'ai' => null,
            'ai_error' => null,
        ];

        $score = $fit['score'];
        $verdict = $fit['verdict'];

        // Claude jen upřesňuje skóre e-shopů. Co e-shop není, neposuzuje,
        // verdikt by stejně nezměnil a dotaz by stál zbytečně.
        if ($measurements['reachable'] && $verdict !== FitVerdict::Poor && $this->ai->enabled()) {
            $scout['ai'] = $this->ai->judge($company, $scout);
            $scout['ai_error'] = $scout['ai'] === null ? $this->ai->lastError() : null;

            if ($scout['ai'] !== null) {
                $scout['ai']['adjustment'] = max(-20, min(20, (int) $scout['ai']['adjustment']));
                $score = max(0, min(100, $score + $scout['ai']['adjustment']));
                $verdict = FitVerdict::fromScore($score);
            }
        }

        $company->forceFill([
            'fit_score' => $score,
            'fit_verdict' => $verdict,
            'scout_data' => $scout,
            'scouted_at' => now(),
            'platform' => $company->platform ?: ($measurements['platform'] ?? null),
            'pain' => $company->pain ?: $this->painFrom($findings, $scout['ai']),
        ])->save();

        $this->addContactFromWebsite($company, $measurements);

        return $company;
    }

    /**
     * Odloží firmu, která se nehodí. Jen z rešerše a jen dokud jsme ji
     * neoslovili. Poptávku, doporučení nebo firmu, se kterou už jednáme,
     * automat neodkládá nikdy.
     */
    public function parkIfRejected(Company $company): bool
    {
        // Čerstvě založená firma nemá výchozí stav z databáze načtený.
        if ($company->status === null) {
            $company->refresh();
        }

        if ($company->status !== CompanyStatus::New
            || $company->source !== CompanySource::Research
            || ! ($company->fit_verdict?->isRejected() ?? false)) {
            return false;
        }

        $company->forceFill(['status' => CompanyStatus::Parked])->save();

        $company->activities()->create([
            'type' => ActivityType::Note,
            'subject' => 'Odloženo automaticky: '.$company->fit_verdict->getLabel().' ('.$company->fit_score.' bodů)',
            'body' => implode("\n", $company->scout_data['reasons'] ?? []),
            'happened_at' => now(),
        ]);

        return true;
    }

    /**
     * Bolest pro šablony zpráv ({{bolest}}). Postřeh od Clauda je
     * formulovaný pro oslovení, jinak poslouží nejzávažnější nález.
     *
     * @param  list<array<string, string>>  $findings
     * @param  array<string, mixed>|null  $ai
     */
    private function painFrom(array $findings, ?array $ai): ?string
    {
        if (filled($ai['hook'] ?? null)) {
            return $ai['hook'];
        }

        return $findings[0]['title'] ?? null;
    }

    /**
     * Kontakt z webu, když firma žádný nemá. Jen e-mail a telefon, jméno
     * z úvodní stránky spolehlivě vyčíst nejde.
     *
     * @param  array<string, mixed>  $m
     */
    private function addContactFromWebsite(Company $company, array $m): void
    {
        $email = $m['emails'][0] ?? null;
        $phone = $m['phones'][0] ?? null;

        if (($email === null && $phone === null) || $company->contacts()->exists()) {
            return;
        }

        $company->contacts()->create([
            'email' => $email,
            'phone' => $phone,
            'is_primary' => true,
            'notes' => '(kontakt z webu)',
        ]);
    }
}
