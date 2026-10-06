<?php

namespace App\Models;

use App\Enums\WorkArea;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Za tenhle měsíc a oblast je klientovi vyfakturováno, na tuhle částku. Viz App\Support\Ads\Billing. */
class ClientInvoice extends Model
{
    protected $guarded = [];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::saving(fn (self $invoice) => $invoice->month = $invoice->month?->startOfMonth());
    }

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'area' => WorkArea::class,
            'amount_czk' => 'integer',
            'invoiced_at' => 'datetime',
        ];
    }
}
