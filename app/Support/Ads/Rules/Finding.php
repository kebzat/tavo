<?php

namespace App\Support\Ads\Rules;

use App\Enums\Ads\AlertSeverity;
use App\Models\Ads\AdAccount;

/**
 * Nález pravidla. Upozornění má říct, co se změnilo a proti čemu (`facts`),
 * z jakého množství dat (`basis`) a co prověřit (`recommendation`).
 */
final class Finding
{
    /** @param  array<string, mixed>  $snapshot */
    public function __construct(
        public readonly AlertSeverity $severity,
        public readonly string $title,
        public readonly string $recommendation,
        public readonly array $snapshot = [],
        public readonly ?AdAccount $account = null,
        public readonly ?string $facts = null,
        public readonly ?string $basis = null,
    ) {}

    public function withSeverity(AlertSeverity $severity): self
    {
        return new self($severity, $this->title, $this->recommendation, $this->snapshot, $this->account, $this->facts, $this->basis);
    }

    public function withTitle(string $title): self
    {
        return new self($this->severity, $title, $this->recommendation, $this->snapshot, $this->account, $this->facts, $this->basis);
    }
}
