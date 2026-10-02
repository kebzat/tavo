<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Společný obsah všech stránek „Potenciální spolupráce". Zatím fotka
 * s nadpisem a krátkým textem před závěrečnou výzvou. Edituje se v panelu
 * nástrojů: Checklisty → Koncepty: společná fotka.
 */
class ProposalSettings extends Settings
{
    /** Cesta k fotce na disku `public`. Prázdné = sekce se nezobrazí. */
    public ?string $photo;

    public ?string $photo_alt;

    public ?string $photo_title;

    public ?string $photo_text;

    public static function group(): string
    {
        return 'proposal';
    }
}
