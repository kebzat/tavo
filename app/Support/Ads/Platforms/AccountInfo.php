<?php

namespace App\Support\Ads\Platforms;

/** Reklamní účet, jak ho vidí platforma. */
final class AccountInfo
{
    public function __construct(
        public readonly string $externalId,
        public readonly string $name,
        public readonly string $currency,
        public readonly ?string $timezone,
        public readonly ?string $status,
        public readonly ?string $business = null,
    ) {}

    /** Řádek do výběru účtu: „Svět čaje · 123456789 · CZK“. */
    public function label(): string
    {
        return collect([$this->name, $this->business, $this->externalId, $this->currency])
            ->filter()
            ->unique()
            ->implode(' · ');
    }
}
