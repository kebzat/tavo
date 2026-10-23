<?php

namespace App\Support\Agent;

use RuntimeException;
use Throwable;

/** GitHub odmítl nebo neodpověděl. Zpráva je česky a jde rovnou do nástrojů. */
class GitHubError extends RuntimeException
{
    public function __construct(string $message, public readonly bool $conflict = false, ?Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);
    }
}
