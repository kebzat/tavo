<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Volný štítek nad rámec číselníků — „teplý kontakt", „HK", „doporučil Pavel". */
class Tag extends Model
{
    protected $table = 'crm_tags';

    protected $guarded = [];

    public $timestamps = true;

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'crm_company_tag', 'tag_id', 'company_id');
    }
}
