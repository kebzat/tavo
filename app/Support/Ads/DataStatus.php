<?php

namespace App\Support\Ads;

use App\Models\Ads\AdAccount;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Čemu z čísel věřit: do kterého dne máme data kompletní, kdy se naposledy
 * stahovalo a jestli to selhalo. Pavel se ráno potřebuje spolehnout, že
 * nula je nula, a ne chybějící stažení.
 *
 * U klienta s víc reklamními účty platí nejslabší článek: data jsou
 * kompletní do dne, kam je mají všechny účty. GA4 se nepočítá, má vlastní
 * sekci a do hlavních čísel nevstupuje.
 */
final class DataStatus
{
    /**
     * Kolik posledních dní Meta ještě dopočítává konverze (atribuce po
     * kliknutí, zpožděné události). Čísla za ně se můžou ráno od rána změnit.
     */
    public const SETTLING_DAYS = 3;

    public const OK = 'ok';

    /** Poslední stažení je starší, chybí aspoň jeden celý den. */
    public const STALE = 'stale';

    /** Poslední stažení skončilo chybou. */
    public const ERROR = 'error';

    /** Účet je propojený, ale ještě se nestahoval. */
    public const WAITING = 'waiting';

    /** Klient nemá zapnutý žádný reklamní účet. */
    public const NONE = 'none';

    private function __construct(
        public readonly string $state,
        public readonly ?CarbonImmutable $completeThrough,
        public readonly ?CarbonImmutable $lastSuccess,
        public readonly ?string $error,
        public readonly bool $demo,
        public readonly bool $refreshPending,
        public readonly CarbonImmutable $today,
    ) {}

    /** @param  Collection<int, AdAccount>  $accounts  všechny účty klienta, vypnuté a GA4 se vynechají */
    public static function for(Collection $accounts, ?CarbonInterface $today = null): self
    {
        $today = CarbonImmutable::parse($today ?? now())->startOfDay();
        $accounts = $accounts->filter(fn (AdAccount $account): bool => $account->is_active && ! $account->isAnalytics())->values();

        if ($accounts->isEmpty()) {
            return new self(self::NONE, null, null, null, false, false, $today);
        }

        $through = $accounts->map(fn (AdAccount $account): ?CarbonImmutable => $account->data_through ? CarbonImmutable::parse($account->data_through)->startOfDay() : null);
        $completeThrough = $through->contains(null) ? null : $through->min();
        $lastSuccess = $accounts->pluck('last_synced_at')->filter()->map(fn ($at): CarbonImmutable => CarbonImmutable::parse($at))->min();
        $error = $accounts->pluck('last_sync_error')->filter()->first();

        $state = match (true) {
            filled($error) => self::ERROR,
            $completeThrough === null => self::WAITING,
            $completeThrough->lt($today->subDay()) => self::STALE,
            default => self::OK,
        };

        return new self(
            $state,
            $completeThrough,
            $lastSuccess,
            $error,
            $accounts->every(fn (AdAccount $account): bool => $account->platform->isDemo()),
            $accounts->contains(fn (AdAccount $account): bool => $account->refresh_requested_at !== null),
            $today,
        );
    }

    /** Data jsou celá až do včerejška a poslední stažení prošlo. */
    public function isFresh(): bool
    {
        return $this->state === self::OK;
    }

    /** Máme čísla za celé období? Bez toho by srovnání a odhady lhaly. */
    public function covers(Period $period): bool
    {
        return $this->completeThrough !== null && $this->completeThrough->gte($period->to);
    }

    /** Kolik dní na konci období chybí. */
    public function missingDays(Period $period): int
    {
        if ($this->covers($period)) {
            return 0;
        }

        if ($this->completeThrough === null || $this->completeThrough->lt($period->from)) {
            return $period->days();
        }

        return (int) $this->completeThrough->diffInDays($period->to);
    }

    /** Den, od kterého se čísla ještě můžou změnit (Meta dopočítává konverze). */
    public function settlingFrom(): ?CarbonImmutable
    {
        return $this->completeThrough?->subDays(self::SETTLING_DAYS - 1);
    }

    /** Stav dne v grafu: ok, ještě se dopočítává, nebo chybí. */
    public function dayState(string $date): string
    {
        $day = CarbonImmutable::parse($date)->startOfDay();

        return match (true) {
            $this->completeThrough === null, $day->gt($this->completeThrough) => 'missing',
            $day->gte($this->settlingFrom()) => 'settling',
            default => 'ok',
        };
    }

    /** Barva štítku ve Filamentu. */
    public function color(): string
    {
        return match ($this->state) {
            self::OK => $this->demo ? 'gray' : 'success',
            self::STALE, self::WAITING => 'warning',
            self::ERROR => 'danger',
            default => 'gray',
        };
    }

    /** Krátký štítek na kartu: „Data do 22. 10.“, „Chyba stahování“. */
    public function badge(): string
    {
        return match ($this->state) {
            self::OK => $this->demo ? 'Ukázková data' : 'Data do '.$this->completeThrough->format('j. n.'),
            self::STALE => 'Data jen do '.$this->completeThrough->format('j. n.'),
            self::ERROR => 'Chyba stahování',
            self::WAITING => 'Čeká na první stažení',
            default => 'Bez reklamního účtu',
        };
    }

    /**
     * Celá věta pod nadpis stránky: do kdy jsou data, kdy se stahovalo,
     * co se ještě může změnit.
     */
    public function sentence(): string
    {
        $synced = $this->lastSuccess ? ' Staženo '.$this->syncedLabel().'.' : '';

        return match ($this->state) {
            self::OK => ($this->demo ? 'Ukázková data, vymyšlená čísla. ' : '')
                .'Data kompletní do '.$this->completeThrough->format('j. n. Y').'.'.$synced
                .' Poslední '.self::SETTLING_DAYS.' dny se ještě můžou změnit, Meta konverze dopočítává.',
            self::STALE => 'Data máme jen do '.$this->completeThrough->format('j. n. Y').', chybí '.$this->staleDays().' '.self::days($this->staleDays()).'.'.$synced
                .' Nula za chybějící dny neznamená, že se nic neutratilo. Odhady a srovnání počítáme jen z kompletních dní.',
            self::ERROR => 'Poslední stažení selhalo: '.$this->error.($this->completeThrough ? ' Data máme do '.$this->completeThrough->format('j. n. Y').'.' : ''),
            self::WAITING => 'Účet je propojený, čísla se stáhnou při nejbližším ranním stažení v 6:00 (nebo tlačítkem Obnovit čísla).',
            default => 'Klient nemá zapnutý žádný reklamní účet.',
        };
    }

    /** „dnes 6:00“, „včera 6:00“, „21. 10. 6:00“. */
    public function syncedLabel(): ?string
    {
        if ($this->lastSuccess === null) {
            return null;
        }

        $at = $this->lastSuccess->timezone('Europe/Prague');

        return match (true) {
            $at->isSameDay($this->today) => 'dnes '.$at->format('G:i'),
            $at->isSameDay($this->today->subDay()) => 'včera '.$at->format('G:i'),
            default => $at->format('j. n. G:i'),
        };
    }

    /** Kolik celých dní do včerejška chybí. */
    public function staleDays(): int
    {
        return $this->completeThrough === null ? 0 : max(0, (int) $this->completeThrough->diffInDays($this->today->subDay()));
    }

    /** @return array<string, mixed> Pro zmrazení do reportu a pro agenta. */
    public function toArray(): array
    {
        return [
            'state' => $this->state,
            'complete_through' => $this->completeThrough?->toDateString(),
            'last_success' => $this->lastSuccess?->toIso8601String(),
            'error' => $this->error,
            'demo' => $this->demo,
        ];
    }

    private static function days(int $count): string
    {
        return match (true) {
            $count === 1 => 'den',
            $count >= 2 && $count <= 4 => 'dny',
            default => 'dní',
        };
    }
}
