<?php

namespace App\Support\Vault;

use App\Enums\VaultType;
use App\Models\VaultEntry;

/** Otevře FTP přístupu z trezoru. Testy podstrčí vlastní instanci přes kontejner. */
class FtpConnector
{
    public function open(VaultEntry $entry): FtpClient
    {
        if ($entry->type !== VaultType::Ftp) {
            throw new VaultException('Tenhle přístup není FTP.');
        }

        return new NativeFtpClient($entry);
    }
}
