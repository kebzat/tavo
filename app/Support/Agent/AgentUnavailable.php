<?php

namespace App\Support\Agent;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use RuntimeException;
use Throwable;

/** Služba agenta neodpovídá, nebo vrátila chybu. Zpráva jde rovnou do upozornění. */
class AgentUnavailable extends RuntimeException
{
    public static function from(?Throwable $e): self
    {
        if ($e instanceof ConnectionException) {
            return new self('Agent na serveru neodpovídá. Běží kontejner taveo-agent?', previous: $e);
        }

        if ($e instanceof RequestException) {
            return new self($e->response->json('chyba') ?: 'Agent vrátil chybu '.$e->response->status().'.', previous: $e);
        }

        return new self('Agent na serveru neodpovídá.', previous: $e);
    }
}
