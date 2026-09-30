<?php

namespace App\Support\Ads;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Období, za které se čísla sčítají, a období, se kterým se porovnává.
 *
 * Dnešek do přednastavených období nepatří, jeho čísla se přes den mění
 * a srovnání by vycházelo vždycky jako propad.
 */
final class Period
{
    public const PRESETS = [
        '7d' => 'Posledních 7 dní',
        '30d' => 'Posledních 30 dní',
        'this_month' => 'Tento měsíc',
        'last_month' => 'Minulý měsíc',
    ];

    private function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly string $key = 'custom',
    ) {}

    public static function between(CarbonInterface|string $from, CarbonInterface|string $to): self
    {
        return new self(CarbonImmutable::parse($from)->startOfDay(), CarbonImmutable::parse($to)->startOfDay());
    }

    public static function preset(?string $key, ?CarbonInterface $today = null): self
    {
        $today = CarbonImmutable::parse($today ?? now())->startOfDay();
        $yesterday = $today->subDay();

        return match ($key) {
            '30d' => new self($yesterday->subDays(29), $yesterday, '30d'),
            // Prvního v měsíci ještě žádný celý den nemáme, ukážeme včerejšek.
            'this_month' => $today->day === 1
                ? new self($yesterday, $yesterday, 'this_month')
                : new self($today->startOfMonth(), $yesterday, 'this_month'),
            'last_month' => new self($today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()->startOfDay(), 'last_month'),
            default => new self($yesterday->subDays(6), $yesterday, '7d'),
        };
    }

    /** Týden pondělí až neděle, do kterého patří zadaný den. */
    public static function week(CarbonInterface $day): self
    {
        $day = CarbonImmutable::parse($day);

        return new self($day->startOfWeek(), $day->endOfWeek()->startOfDay());
    }

    /** Celý kalendářní měsíc, do kterého patří zadaný den. */
    public static function month(CarbonInterface $day): self
    {
        $day = CarbonImmutable::parse($day);

        return new self($day->startOfMonth(), $day->endOfMonth()->startOfDay());
    }

    /**
     * Srovnávací období. Měsíce se porovnávají se stejnými dny minulého
     * měsíce (1.–29. září s 1.–29. srpnem), ostatní s předchozím úsekem
     * stejné délky.
     */
    public function previous(): self
    {
        if ($this->from->day === 1 && $this->from->isSameMonth($this->to)) {
            $from = $this->from->subMonthNoOverflow();

            // Celý měsíc se srovnává s celým minulým, i když má jiný počet dní.
            if ($this->to->isSameDay($this->to->endOfMonth())) {
                return new self($from, $from->endOfMonth()->startOfDay());
            }

            $to = $from->addDays($this->days() - 1);

            return new self($from, $to->isSameMonth($from) ? $to : $from->endOfMonth()->startOfDay());
        }

        return new self($this->from->subDays($this->days()), $this->from->subDay());
    }

    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to) + 1;
    }

    public function contains(CarbonInterface $day): bool
    {
        return $day->between($this->from, $this->to);
    }

    /** @return list<string> Data ve formátu Y-m-d, den po dni. */
    public function dates(): array
    {
        $dates = [];

        for ($day = $this->from; $day->lte($this->to); $day = $day->addDay()) {
            $dates[] = $day->toDateString();
        }

        return $dates;
    }

    /** „22.–28. 9. 2026“, „28. 9. – 4. 10. 2026“, „30. 12. 2025 – 5. 1. 2026“. */
    public function label(): string
    {
        if ($this->from->isSameDay($this->to)) {
            return $this->from->format('j. n. Y');
        }

        if ($this->from->isSameMonth($this->to)) {
            return $this->from->format('j.').'–'.$this->to->format('j. n. Y');
        }

        if ($this->from->isSameYear($this->to)) {
            return $this->from->format('j. n.').' – '.$this->to->format('j. n. Y');
        }

        return $this->from->format('j. n. Y').' – '.$this->to->format('j. n. Y');
    }

    /**
     * Meze pro whereBetween nad sloupcem `date`. Horní mez nese čas, aby
     * prošel i den uložený jako „2026-09-07 00:00:00“ (SQLite v testech).
     *
     * @return array{0: string, 1: string}
     */
    public function bounds(): array
    {
        return [$this->from->toDateString(), $this->to->toDateString().' 23:59:59'];
    }

    /** @return array{from: string, to: string} */
    public function toArray(): array
    {
        return ['from' => $this->from->toDateString(), 'to' => $this->to->toDateString()];
    }
}
