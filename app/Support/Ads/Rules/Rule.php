<?php

namespace App\Support\Ads\Rules;

/**
 * Jedno pravidlo denní kontroly. Vrací nálezy s konkrétním doporučením.
 * Když pravidlo přestane platit, upozornění se samo zavře.
 */
interface Rule
{
    /** Stálý klíč, podle něj se páruje upozornění mezi dny. */
    public function key(): string;

    /** @return list<Finding> */
    public function evaluate(AlertContext $context): array;
}
