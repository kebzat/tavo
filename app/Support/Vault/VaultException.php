<?php

namespace App\Support\Vault;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Chyba při práci s přístupem. Text jde rovnou člověku i agentovi, proto česky a bez hesel. */
class VaultException extends RuntimeException
{
    /** Nezachycená (třeba špatná cesta od agenta) se vrátí jako 422, ne jako chyba serveru. */
    public function render(): JsonResponse
    {
        return response()->json(['ok' => false, 'chyba' => $this->getMessage()], 422, options: JSON_UNESCAPED_UNICODE);
    }
}
