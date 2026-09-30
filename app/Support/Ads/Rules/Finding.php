<?php

namespace App\Support\Ads\Rules;

use App\Enums\Ads\AlertSeverity;
use App\Models\Ads\AdAccount;

final class Finding
{
    /** @param  array<string, mixed>  $snapshot */
    public function __construct(
        public readonly AlertSeverity $severity,
        public readonly string $title,
        public readonly string $recommendation,
        public readonly array $snapshot = [],
        public readonly ?AdAccount $account = null,
    ) {}
}
