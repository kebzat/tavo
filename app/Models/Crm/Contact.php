<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Osoba ve firmě. Z rešerše často známe jen e-mail nebo telefon. */
class Contact extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'crm_contacts';

    protected $guarded = [];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Popisek do roletek — jméno, a když chybí, tak aspoň e-mail. */
    public function label(): string
    {
        return $this->name ?: ($this->email ?: ($this->phone ?: 'Kontakt #'.$this->getKey()));
    }

    protected static function booted(): void
    {
        // Primární kontakt může být jen jeden. Bez tohohle by po označení
        // druhého kontaktu byly primární dva a šablony by braly náhodný.
        static::saved(function (self $contact): void {
            if (! $contact->is_primary) {
                return;
            }

            self::query()
                ->where('company_id', $contact->company_id)
                ->whereKeyNot($contact->getKey())
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        });
    }

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }
}
