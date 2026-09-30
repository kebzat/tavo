<?php

namespace App\Support\Ads\Platforms;

use RuntimeException;

/** Platforma vrátila chybu. Zpráva jde do logu synchronizace a do upozornění, proto česky. */
class AdsApiException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $authFailed = false)
    {
        parent::__construct($message);
    }
}
